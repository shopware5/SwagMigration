<?php
/**
 * Shopware 4.0
 * Copyright © 2013 shopware AG
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
 * Shopware SwagMigration Components - Price
 *
 * Price import adapter
 *
 * @category  Shopware
 * @package Shopware\Plugins\SwagMigration\Components
 * @copyright Copyright (c) 2012, shopware AG (http://www.shopware.de)
 */
class Shopware_Components_Migration_Import_Price extends Shopware_Components_Migration_Import_Base
{

    /**
     * Returns the default error message for this import class
     * @return mixed
     */
    public function getDefaultErrorMessage()
    {
        return $this->getNameSpace()->get('errorImportingProducts', "An error occurred while importing products");

    }

    /**
     * Returns the progress message for the current import step. A Progress-Object will be passed, so
     * you can get some context info for your snippet
     *
     * @param Shopware_Components_Migration_Import_Progress $progress
     * @return string
     */
    public function getCurrentProgressMessage($progress)
    {
        return sprintf($this->getNameSpace()->get('progressPrices', "%s out of %s prices imported"), $this->getProgress()->getOffset(), $this->getProgress()->getCount());
    }

    /**
     * Returns the default 'all done' message
     * @return mixed
     */
    public function getDoneMessage()
    {
        return $this->getNameSpace()->get('importedPrices', "Prices successfully imported!");
    }


    /**
     * Main run method of each import adapter. The run method will query the source profile, iterate
     * the results and prepare the data for import via the old Shopware API.
     *
     * If you want to import multiple entities with one import-class, you might want to check for
     * $this->getInternalName() in order to distinct which (sub)entity you where called for.
     *
     * The run method may only return instances of Shopware_Components_Migration_Import_Progress
     * The calling instance will use those progress object to communicate with the ExtJS backend.
     * If you want this to work properly, think of calling:
     * - $this->initTaskTimer() at the beginning of your run method
     * - $this->getProgress()->setCount(222) to set the total number of data
     * - $this->increaseProgress() to increase the offset/progress
     * - $this->getProgress()->getOffset() to get the current progress' offset
     * - return $this->getProgress()->error("Message") in order to stop with an error message
     * - return $this->getProgress() in order to be called again with the current offset
     * - return $this->getProgress()->done() in order to mark the import as finished
     *
     *
     * @return Shopware_Components_Migration_Import_Progress
     */
    public function run()
    {
        $offset = $this->getProgress()->getOffset();

        // Reset block-prices on the first request. (SW-5471)
        if ($offset === 0) {
            $sql = '
			DELETE FROM s_articles_prices WHERE `from` > 1;
		    UPDATE s_articles_prices SET `to` = "beliebig"
		    ';
            Shopware()->Db()->query($sql);
        }

        $result = $this->Source()->queryProductPrices($offset);
        $count = $result->rowCount()+$offset;
        $this->getProgress()->setCount($count);

        $taskStartTime  = $this->initTaskTimer();

        while ($price = $result->fetch()) {
            if(!empty($this->Request()->price_group) && !empty($price['pricegroup'])) {
                if(isset($this->Request()->price_group[$price['pricegroup']])) {
                    $price['pricegroup'] = $this->Request()->price_group[$price['pricegroup']];
                } else {
                    continue;
                }
            }
            if(empty($price['pricegroup'])) {
                $price['pricegroup'] = 'EK';
            }

            $sql = "
                SELECT ad.id as articledetailsID, IF(cg.taxinput=1, t.tax, 0) as tax
                FROM s_plugin_migrations pm
                JOIN s_articles_details ad
                ON ad.id=pm.targetID
                JOIN s_articles a
                ON a.id=ad.articleID
                JOIN s_core_tax t
                ON t.id=a.taxID
                INNER JOIN s_core_customergroups cg
                ON cg.mode=0
                AND cg.groupkey=?
                WHERE pm.sourceID=?
                AND pm.typeID=?
            ";
            $price_config = Shopware()->Db()->fetchRow($sql, array($price['pricegroup'], $price['productID'], Shopware_Components_Migration_Helpers::MAPPING_ARTICLE));
            if(!empty($price_config)) {
                $price = array_merge($price, $price_config);
                if(isset($price['net_price'])) {
                    if(empty($price['tax'])) {
                        $price['price'] = $price['net_price'];
                        unset($price['net_price'], $price['tax']);
                    } else {
                        $price['price'] = round($price['net_price']*(100+$price['tax'])/100, 2);
                        unset($price['net_price']);
                    }
                }
                if(isset($price['net_pseudoprice'])) {
                    if(empty($price['tax'])) {
                        $price['pseudoprice'] = $price['net_pseudoprice'];
                        unset($price['net_pseudoprice'], $price['tax']);
                    } else {
                        $price['pseudoprice'] = round($price['net_pseudoprice']*(100+$price['tax'])/100, 2);
                        unset($price['net_pseudoprice']);
                    }
                }

                $price['articlepricesID'] = Shopware()->Api()->Import()->sArticlePrice($price);
            }


            $this->increaseProgress();
            if ($this->newRequestNeeded()) {
                return $this->getProgress();
            }
        }

        return $this->getProgress()->done();
    }
}