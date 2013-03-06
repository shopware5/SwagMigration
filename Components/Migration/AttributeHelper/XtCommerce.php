<?php
/**
 * Shopware 4.0
 * Copyright © 2012 shopware AG
 *
 * According to our dual licensing model, this program can be used either
 * under the terms of the GNU Affero General Public License, version 3,
 * or under a proprietary license.
 *
 * The texts of the GNU Affero General Public License with an additional
 * permission and of our proprietary license can be found at and
 * in the LICENSE file you have received along with this program.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * "Shopware" is a registered trademark of shopware AG.
 * The licensing of the program under the AGPLv3 does not imply a
 * trademark license. Therefore any rights, title and interest in
 * our trademarks remain entirely with us.
 */

/**
 * Shopware SwagMigration Components - XtCommerce
 *
 * @category  Shopware
 * @package Shopware\Plugins\SwagMigration\Components
 * @copyright Copyright (c) 2012, shopware AG (http://www.shopware.de)
 */
class Shopware_Components_Migration_AttributeHelper_XtCommerce extends Shopware_Components_Migration_AttributeHelper
{
    public function generateVariants($offset)
    {
        $requestTime = !empty($_SERVER['REQUEST_TIME']) ? $_SERVER['REQUEST_TIME'] : time();

        $products_result = $this->Source()->queryAttributedProducts($offset);
        if (empty($products_result)) {
            return true;
        }
        $count = $products_result->rowCount()+$offset;

        while ($product = $products_result->fetch()) {
            $id = $product['productID'];
            $result = $this->Source()->queryProductAttributes($id);

            $this->createVariantsFromAttributes($id, $result);

            $offset++;
            if(time()-$requestTime >= 10) {
                return array(
                    'offset' => $offset,
                    'count' => $count
                );
            }

        }

        return true;

    }

    public function createVariantsFromAttributes($productId, $attributes)
    {

        list($attributes, $attributesByGroup) = $this->prepareAttributes($attributes);

        // Create cartesian product
        $allVariants = $this->createCartesianProduct($attributesByGroup);

        // get an array with variant configuration
        $properties = $this->getVariantsConfiguration($allVariants, $attributes);

        // Get base article information
        $info = $this->getBaseArticleInfo($productId);
        // info is empty if article was not imported before
        if (empty($info)) {
            return;
        }
        $numberMainDetail = $info['ordernumber'];
        $productId = $info['productId'];
        $supplierId = $info['supplierId'];
        $oldMainDetail = $info['oldMainDetailId'];

        // Generate number if non was set before
        if (empty($numberMainDetail)) {
            $numberMainDetail = uniqid();
        }

        $numberCounter = 1;
        foreach ($allVariants as $variantsKey => $variantOptions) {
            $options = array();
            foreach ($variantOptions as $key => $option) {
                $options[] = $attributes[$option]['name'];

            }
            $groups = implode("|", array_keys($variantOptions));
            $options = implode("|", $options);

            $number = $numberMainDetail.'.'.$numberCounter;
            $isMain = $numberCounter === 1 ? true : false;

            $articleDetailId = $this->createVariant($productId, $number, $options, $groups, $supplierId, $isMain);
            $this->createVariantPrices($articleDetailId, $properties[$variantsKey]['price'], $oldMainDetail);
            if ($isMain) {
                Shopware()->Db()->query("UPDATE s_articles SET main_detail_id = ? WHERE id = ?", array($articleDetailId, $productId));
                Shopware()->Db()->query("UPDATE s_articles_details SET kind = 1 WHERE id = ?", array($articleDetailId));
            }

            $numberCounter++;
        }

        // Delete old main detaild
        $this->deleteDetail($oldMainDetail);
    }

    public function getVariantsConfiguration($variants, $attributes)
    {
        $properties = array();
        foreach ($variants as $key => $options) {
            $properties[$key] = array('price' => 0, 'weight' => 0);
            foreach ($options as $groupName => $optionId) {
                if ($attributes[$optionId]['priceMode'] = '+') {
                    $properties[$key]['price'] += $attributes[$optionId]['price'];
                }else{
                    $properties[$key]['price'] -= $attributes[$optionId]['price'];
                }
                if ($attributes[$optionId]['weightMode'] = '+') {
                    $properties[$key]['weight'] += $attributes[$optionId]['weight'];
                }else{
                    $properties[$key]['weight']['price'] -= $attributes[$optionId]['weight'];
                }
            }
        }

        return $properties;
    }

    public function prepareAttributes($attributes)
    {
        $groups = array();
        $allOptions = array();

        // Exploded grouped options and get an array with groups as keys and options as values
        foreach ($attributes as $group => $row) {
            $options = explode('|', $row['attribute_groupoption']);
            $priceModes = explode('|', $row['attribute_priceMode']);
            $weightModes = explode('|', $row['attribute_weightMode']);
            $prices = explode('|', $row['attribute_price']);
            $weights = explode('|', $row['attribute_weight']);

            $groupOptions = array();
            foreach ($options as $key => $option) {
                $cur = array('name'       => $option,
                             'price'      => $prices[$key],
                             'weight'     => $weights[$key],
                             'priceMode' => $priceModes[$key],
                             'weightMode' => $weightModes[$key]
                );
                $groupOptions[] = array_push($allOptions, $cur)-1;

            }
            $groups[$group] = $groupOptions;
        }

        return array($allOptions, $groups );


    }

    public function deleteDetail($detailId)
    {
        $sql = "DELETE detail, prices, price_attributes, attributes
                FROM s_articles_details detail
                LEFT JOIN s_articles_prices prices
                    ON prices.articledetailsID = detail.id
                LEFT JOIN s_articles_attributes attributes
                    ON  attributes.articledetailsID = detail.id
                LEFT JOIN s_articles_prices_attributes price_attributes
                    ON price_attributes.priceID = prices.id
                WHERE detail.id = ?";
        Shopware()->Db()->query($sql, $detailId);
    }

    public function getBaseArticleInfo($productId)
    {
        $sql = '
            SELECT
                ad.id as oldMainDetailId,
                ad.articleID as productId,
                ad.ordernumber,
                a.supplierID as supplierId
            FROM s_plugin_migrations pm
            LEFT JOIN s_articles_details ad
                ON ad.id=pm.targetID
            LEFT JOIN s_articles a
                ON a.id = ad.articleID
            WHERE pm.`sourceID`=?
            AND `typeID`=1
        ';
        return Shopware()->Db()->fetchRow($sql, array($productId));
    }

    public function createVariant($mainId, $number, $additionaltext, $groups, $supplierId, $isMain=false)
    {

        $data = array(
           'active' => 1,

           'additionaltext' => $additionaltext,
           'variant_group_names' => $groups,
           'mainID' =>$mainId,
           'kind' => $isMain ? 1 : 2,
           'supplierID' => $supplierId,
           'ordernumber' => $number
        );
        $result = Shopware()->Api()->Import()->sArticle($data);

        if($result === false) {
            error_log(print_r(Shopware()->Api()->sGetLastError(), true));
        }

        return $result['articledetailsID'];

    }

    public function createVariantPrices($detailId, $priceDiff, $oldMainDetail)
    {
        $sql = "
            INSERT INTO s_articles_prices
                (`pricegroup`, `from`, `to`, `articleID`, `articledetailsID`, `price`, `pseudoprice`, `baseprice`, `percent`)
            SELECT
                `pricegroup`, `from`, `to`, `articleID`, {$detailId} as articledetailsID, `price` + {$priceDiff} as price, `pseudoprice`, `baseprice`, `percent`
            FROM s_articles_prices
            WHERE articledetailsID = ?
        ";
        Shopware()->Db()->query($sql, array($oldMainDetail));
    }
}