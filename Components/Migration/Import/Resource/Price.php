<?php
/**
 * (c) shopware AG <info@shopware.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Shopware\SwagMigration\Components\Migration\Import\Resource;

use Shopware\SwagMigration\Components\Migration;
use Shopware\SwagMigration\Components\DbServices\Import\Import;
use Shopware\SwagMigration\Components\Migration\Import\Progress;

/**
 * Shopware SwagMigration Components - Price
 *
 * Price import adapter
 *
 * @category  Shopware
 * @package Shopware\Plugins\SwagMigration\Components\Migration\Import\Resource
 * @copyright Copyright (c) 2012, shopware AG (http://www.shopware.de)
 */
class Price extends AbstractResource
{
    /**
     * @inheritdoc
     */
    public function getDefaultErrorMessage()
    {
        return $this->getNameSpace()->get('errorImportingPrices', "An error occurred while importing prices");
    }

    /**
     * @inheritdoc
     */
    public function getCurrentProgressMessage(Progress $progress)
    {
        return sprintf(
            $this->getNameSpace()->get('progressPrices', "%s out of %s prices imported"),
            $this->getProgress()->getOffset(),
            $this->getProgress()->getCount()
        );
    }

    /**
     * @inheritdoc
     */
    public function getDoneMessage()
    {
        return $this->getNameSpace()->get('importedPrices', "Prices successfully imported!");
    }

    /**
     * @inheritdoc
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
        $count = $result->rowCount() + $offset;
        $this->getProgress()->setCount($count);

        $this->initTaskTimer();

        /* @var Import $import */
        $import = Shopware()->Container()->get('swagmigration.import');

        while ($price = $result->fetch()) {
            if (!empty($this->Request()->price_group) && !empty($price['pricegroup'])) {
                if (isset($this->Request()->price_group[$price['pricegroup']])) {
                    $price['pricegroup'] = $this->Request()->price_group[$price['pricegroup']];
                } else {
                    continue;
                }
            }
            if (empty($price['pricegroup'])) {
                $price['pricegroup'] = 'EK';
            }

            $sql = "
                SELECT ad.id AS articledetailsID, IF(cg.taxinput=1, t.tax, 0) AS tax
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
            $price_config = Shopware()->Db()->fetchRow(
                $sql,
                [
                    $price['pricegroup'],
                    $price['productID'],
                    Migration::MAPPING_ARTICLE
                ]
            );
            if (!empty($price_config)) {
                $price = array_merge($price, $price_config);
                if (isset($price['net_price'])) {
                    if (empty($price['tax'])) {
                        $price['price'] = $price['net_price'];
                        unset($price['net_price'], $price['tax']);
                    } else {
                        $price['price'] = round($price['net_price'] * (100 + $price['tax']) / 100, 2);
                        unset($price['net_price']);
                    }
                }
                if (isset($price['net_pseudoprice'])) {
                    if (empty($price['tax'])) {
                        $price['pseudoprice'] = $price['net_pseudoprice'];
                        unset($price['net_pseudoprice'], $price['tax']);
                    } else {
                        $price['pseudoprice'] = round($price['net_pseudoprice'] * (100 + $price['tax']) / 100, 2);
                        unset($price['net_pseudoprice']);
                    }
                }

                $price['articlepricesID'] = $import->articlePrice($price);
            }


            $this->increaseProgress();
            if ($this->newRequestNeeded()) {
                return $this->getProgress();
            }
        }

        return $this->getProgress()->done();
    }
}
