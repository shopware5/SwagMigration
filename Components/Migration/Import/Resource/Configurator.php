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
 * Shopware SwagMigration Components - Configurator
 *
 * Configurator import adapter
 *
 * @category  Shopware
 * @package Shopware\Plugins\SwagMigration\Components\Migration\Import\Resource
 * @copyright Copyright (c) 2012, shopware AG (http://www.shopware.de)
 */
class Shopware_Components_Migration_Import_Resource_Configurator extends Shopware_Components_Migration_Import_Resource_Abstract
{
    /**
     * Returns the default error message for this import class
     *
     * @return mixed
     */
    public function getDefaultErrorMessage()
    {
        return $this->getNameSpace()->get(
            'errorGeneratingVariantsFromAttributes',
            "An error occurred while generating configuratos"
        );
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
        return sprintf(
            $this->getNameSpace()->get('configuratorProgress', "%s out of %s configurators imported"),
            $this->getProgress()->getOffset(),
            $this->getProgress()->getCount()
        );
    }

    /**
     * Returns the default 'all done' message
     *
     * @return mixed
     */
    public function getDoneMessage()
    {
        return $this->getNameSpace()->get('generatedConfigurators', "Configurators successfully generated!");
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

        $products_result = $this->Source()->queryAttributedProducts($offset);
        if (empty($products_result)) {
            $this->getProgress()->addRequestParam('import_generate_variants', null);
            $this->getProgress()->addRequestParam('import_create_configurator_variants', null);

            return $this->getProgress()->done();
        }

        $count = $products_result->rowCount() + $offset;
        $this->getProgress()->setCount($count);
        $this->initTaskTimer();

        // iterate all products with attributes
        while ($product = $products_result->fetch()) {
            $id = $product['productID'];

            // Skip products which have not been imported before
            $productId = $this->getBaseArticleInfo($id);
            if (false === $productId) {
                continue;
            }

            // Create configurator set for product
            $configuratorSetName = "Generated Set - " . $id;
            $configuratorSetId = Shopware()->Db()->fetchOne(
                "
                SELECT `id`
                FROM `s_article_configurator_sets`
                WHERE `name`='{$configuratorSetName}' LIMIT 1"
            );
            if (false === $configuratorSetId) {
                $sql = "INSERT INTO s_article_configurator_sets SET `name`='{$configuratorSetName}'";
                Shopware()->Db()->query($sql);
                $configuratorSetId = Shopware()->Db()->lastInsertId();
            }

            // Get all attributes of the current product
            $result = $this->Source()->queryProductAttributes($id);

            $options = array();
            $groups = array();
            // iterate all attributes
            while ($attribute = $result->fetch()) {
                $group = $attribute['group_name'];
                $option = $attribute['option_name'];
                $price = $attribute['price'];
                $configurator_type = !empty($attribute['configurator_type']) ? (int) $attribute['configurator_type'] : 0;
                $group_position = !empty($attribute['group_position']) ? (int) $attribute['group_position'] : 0;
                $option_position = !empty($attribute['option_position']) ? (int) $attribute['option_position'] : 0;

                // Create / load group
                if (!isset($groups[$group])) {
                    $groupId = Shopware()->Db()->fetchOne(
                        "
                        SELECT `id`
                        FROM `s_article_configurator_groups`
                        WHERE `name`='{$group}' LIMIT 1"
                    );
                    if ($groupId === false) {
                        $sql = "INSERT INTO `s_article_configurator_groups` (`name`, `position`) VALUES ('{$group}', {$group_position})";
                        Shopware()->Db()->query($sql);
                        $groupId = Shopware()->Db()->lastInsertId();
                    }
                    $groups[$group] = $groupId;
                } else {
                    $groupId = $groups[$group];
                }

                // Set group relations
                $sql = "INSERT IGNORE INTO s_article_configurator_set_group_relations
                    (`set_id`, `group_id`)
                    VALUES ({$configuratorSetId}, {$groupId})
                ";
                Shopware()->Db()->query($sql);

                // Create / load option
                if (!isset($options[$option])) {
                    $optionId = Shopware()->Db()->fetchOne(
                        "
                        SELECT `id`
                        FROM `s_article_configurator_options`
                        WHERE `name`='{$option}' AND `group_id`={$groupId}"
                    );
                    if ($optionId === false) {
                        $sql = "INSERT INTO `s_article_configurator_options` (`group_id`, `name`, `position`) VALUES ({$groupId}, '{$option}', {$option_position})";
                        Shopware()->Db()->query($sql);
                        $optionId = Shopware()->Db()->lastInsertId();
                    }
                    $options[$option] = $optionId;
                } else {
                    $optionId = $options[$option];
                }

                // Set option relations
                $sql = "INSERT IGNORE INTO s_article_configurator_set_option_relations (`set_id`, `option_id`) VALUES ({$configuratorSetId}, {$optionId})";
                Shopware()->Db()->query($sql);

                if ($price) {
                    if (version_compare(Shopware::VERSION, '5.0', '>=') || Shopware::VERSION == "___VERSION___") {
                        $sql = "INSERT INTO `s_article_configurator_price_variations` (`configurator_set_id`, `options`, `variation`) VALUES ({$configuratorSetId}, CONCAT('|', {$optionId}, '|'), {$price})";
                    } elseif (version_compare(Shopware::VERSION, '4.4', '>=')) {
                        $sql = "INSERT INTO `s_article_configurator_price_surcharges` (`configurator_set_id`, `options`, `surcharge`) VALUES ({$configuratorSetId}, CONCAT('|', {$optionId}, '|'), {$price})";
                    } else {
                        $sql = "INSERT INTO `s_article_configurator_price_surcharges` (`configurator_set_id`, `parent_id`, `surcharge`) VALUES ({$configuratorSetId}, {$optionId}, {$price})";
                    }
                    Shopware()->Db()->query($sql);
                }
            }

            // Set product's configurator set
            $sql = "UPDATE s_articles SET configurator_set_id = {$configuratorSetId} WHERE `id`={$productId}";
            Shopware()->Db()->query($sql);

            // Finally set the type of the configurator
            if ($configurator_type > 0) {
                $sql = "UPDATE `s_article_configurator_sets` SET `type`={$configurator_type} WHERE `id`={$configuratorSetId}";
                Shopware()->Db()->query($sql);
            }

            $this->increaseProgress();
            if ($this->newRequestNeeded()) {
                return $this->getProgress();
            }
        }

        // Set variant generation to be the next step
        $this->getProgress()->addRequestParam('import_create_configurator_variants', 1);

        return $this->getProgress()->done();
    }
}
