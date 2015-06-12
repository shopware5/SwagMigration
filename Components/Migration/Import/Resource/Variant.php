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
 * Shopware SwagMigration Components - Variant
 *
 * Variant import adapter
 *
 * @category  Shopware
 * @package Shopware\Plugins\SwagMigration\Components\Migration\Import\Resource
 * @copyright Copyright (c) 2012, shopware AG (http://www.shopware.de)
 */
class Shopware_Components_Migration_Import_Resource_Variant extends Shopware_Components_Migration_Import_Resource_Abstract
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
            "An error occurred while generating configurator variants"
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
            $this->getNameSpace()->get('variantsArticleProgress', "Generating variants for product %s out of %s"),
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
        return $this->getNameSpace()->get('generatedVariants', "Variants successfully generated");
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
        $offsetProduct = $this->getProgress()->getOffset();

        // Get products with attributes
        $products_result = $this->Source()->queryAttributedProducts($offsetProduct);
        if (empty($products_result)) {
            $this->getProgress()->addRequestParam('import_generate_variants', null);
            $this->getProgress()->addRequestParam('import_create_configurator_variants', null);

            return $this->getProgress()->done();
        }

        $count = $products_result->rowCount() + $offsetProduct;
        $this->getProgress()->setCount($count);
        $this->initTaskTimer();

        // iter products
        while ($product = $products_result->fetch()) {
            $id = $product['productID'];

            // continue if product was not imported before
            $productId = $this->getBaseArticleInfo($id);
            if (false === $productId) {
                continue;
            }

            $groups = $this->getConfiguratorGroups($productId);

            $params = array(
                'articleId' => $productId,
                'groups' => $groups

            );

            $this->increaseProgress();

            // Return the groups
            // The ExtJS frontend will care of the generation by triggering
            // the default article controller

            $this->getProgress()->addRequestParam('params', $params);
            $this->getProgress()->addRequestParam('create_variants', true);

            return $this->getProgress();
        }

        echo $this->getProgress()->done();
    }

    /**
     * Helper function which gets the configurator groups for
     * a given product
     *
     * @param $productId
     * @return Array
     */
    public function getConfiguratorGroups($productId)
    {
        // get configurator groups for the given product
        $builder = Shopware()->Models()->createQueryBuilder();
        $builder->select(array('PARTIAL article.{id}', 'configuratorSet', 'groups'))
                ->from('Shopware\Models\Article\Article', 'article')
                ->innerJoin('article.configuratorSet', 'configuratorSet')
                ->leftJoin('configuratorSet.groups', 'groups')
                ->where('article.id = ?1')
                ->setParameter(1, $productId);

        $result = array_pop($builder->getQuery()->getArrayResult());

        $configuratorArray = $result['configuratorSet'];
        $groups = $configuratorArray['groups'];

        // Additionally get the options for the given configurator set
        // this relation seems not to be available in the configurator models
        // (the configuratorSet-Model returns all group's options, even those
        // not related to the given set)
        $sql = "SELECT options.group_id, TRUE AS active, options.id FROM `s_article_configurator_sets` sets

	     LEFT JOIN s_article_configurator_set_option_relations relations
	     ON relations.set_id = sets.id

	     LEFT JOIN s_article_configurator_options options
	     ON options.id = relations.option_id

	     WHERE sets.id = ?";
        $results = Shopware()->Db()->fetchAll($sql, array($configuratorArray['id']));

        // Sort the options by group
        $optionsByGroups = array();
        foreach ($results as $option) {
            $groupId = $option['group_id'];
            if (!isset($optionsByGroups[$groupId])) {
                $optionsByGroups[$groupId] = array();
            }
            $optionsByGroups[$groupId][] = $option;
        }

        // merge the options into the group
        $totalCount = 1;
        foreach ($groups as &$group) {
            $group['options'] = $optionsByGroups[$group['id']];
            if (count($group['options']) > 0) {
                $totalCount = $totalCount * count($group['options']);
            }
        }

        return $groups;
    }
}
