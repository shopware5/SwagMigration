<?php
/**
 * (c) shopware AG <info@shopware.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Shopware\SwagMigration\Components\Migration\Import\Resource;

use Shopware\SwagMigration\Components\Migration\Import\Progress;
use Shopware\SwagMigration\Components\Migration;
use Shopware\SwagMigration\Components\DbServices\Import\Import;

/**
 * Shopware SwagMigration Components - Translation
 *
 * Translation import adapter
 *
 * @category  Shopware
 * @package Shopware\Plugins\SwagMigration\Components\Migration\Import\Resource
 * @copyright Copyright (c) 2012, shopware AG (http://www.shopware.de)
 */
class Translation extends AbstractResource
{
    /**
     * @inheritdoc
     */
    public function getDefaultErrorMessage()
    {
        return $this->getNameSpace()->get(
            'errorImportingTranslations',
            "An error occurred while importing translations"
        );
    }

    /**
     * @inheritdoc
     */
    public function getCurrentProgressMessage(Progress $progress)
    {
        return sprintf(
            $this->getNameSpace()->get('progressTranslations', "%s out of %s translations imported"),
            $this->getProgress()->getOffset(),
            $this->getProgress()->getCount()
        );
    }

    /**
     * @inheritdoc
     */
    public function getDoneMessage()
    {
        return $this->getNameSpace()->get('importedTranslations', "Translations successfully imported!");
    }

    /**
     * @inheritdoc
     */
    public function run()
    {
        $offset = $this->getProgress()->getOffset();

        $result = $this->Source()->queryProductTranslations($offset);
        $count = $result->rowCount() + $offset;
        $this->getProgress()->setCount($count);

        $this->initTaskTimer();

        /* @var Import $import */
        $import = Shopware()->Container()->get('swagmigration.import');

        while ($translation = $result->fetch()) {
            //Attribute
            if (!empty($this->Request()->attribute)) {
                foreach ($this->Request()->attribute as $source => $target) {
                    if (!empty($target) && isset($translation[$source])) {
                        $translation[$target] = $translation[$source];
                        unset($translation[$source]);
                    }
                }
            }

            //set the language id of the translation
            if (isset($this->Request()->language[$translation['languageID']])) {
                $translation['languageID'] = $this->Request()->language[$translation['languageID']];
            }

            //prevent productId from being double
            if (stristr($translation["productID"], "e")) {
                $translation['productID'] = "'" . $translation['productID'] . "'";
            }

            //get the product data
            $sql = '
                SELECT ad.articleID, ad.id AS articledetailsID, kind
                FROM s_plugin_migrations pm
                JOIN s_articles_details ad
                ON ad.id=pm.targetID
                WHERE pm.`sourceID`= ?
                AND (typeID = ? OR typeID = ?)
            ';

            $product_data = Shopware()->Db()->fetchRow(
                $sql,
                [
                    $translation['productID'],
                    Migration::MAPPING_ARTICLE,
                    Migration::MAPPING_VALID_NUMBER
                ]
            );

            if (empty($product_data) || $product_data === false) {
                $sql = '
                    SELECT ad.articleID, ad.id AS articledetailsID, kind
                    FROM s_plugin_migrations pm
                    JOIN s_articles_details ad
                    ON ad.id=pm.targetID
                    WHERE pm.`sourceID` LIKE ?
                    AND (typeID = ? OR typeID = ?)
                ';

                $product_data = Shopware()->Db()->fetchRow(
                    $sql,
                    [
                        $translation['productID'],
                        Migration::MAPPING_ARTICLE,
                        Migration::MAPPING_VALID_NUMBER
                    ]
                );
            }

            if (!empty($product_data)) {
                $translation['articletranslationsID'] = $import->translation(
                    $product_data['kind'] == 1 ? 'article' : 'variant',
                    $product_data['kind'] == 1 ? $product_data['articleID'] : $product_data['articledetailsID'],
                    $translation['languageID'],
                    $translation
                );
            }

            $this->increaseProgress();
            if ($this->newRequestNeeded()) {
                return $this->getProgress();
            }
        }

        return $this->getProgress()->done();
    }
}
