<?php
/**
 * (c) shopware AG <info@shopware.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Shopware\SwagMigration\Components\Migration\Import\Resource;

use Shopware\Models\Article\Article;
use Shopware\SwagMigration\Components\Migration\Import\Progress;
use Shopware\SwagMigration\Components\Normalizer\WooCommerce;

class Variant extends AbstractResource
{
    /**
     * {@inheritdoc}
     */
    public function getDefaultErrorMessage()
    {
        return $this->getNameSpace()->get(
            'errorGeneratingVariantsFromAttributes',
            'An error occurred while generating configurator variants'
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getCurrentProgressMessage(Progress $progress)
    {
        return sprintf(
            $this->getNameSpace()->get('variantsArticleProgress', 'Generating variants for product %s out of %s'),
            $this->getProgress()->getOffset(),
            $this->getProgress()->getCount()
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getDoneMessage()
    {
        return $this->getNameSpace()->get('generatedVariants', 'Variants successfully generated');
    }

    /**
     * {@inheritdoc}
     */
    public function run()
    {
        $offsetProduct = $this->getProgress()->getOffset();
        $call = array_merge($this->Request()->getPost(), $this->Request()->getQuery());

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

        if ($call['profile'] !== 'WooCommerce') {
            while ($product = $products_result->fetch()) {
                $this->migrateVariant($product);
            }
        } elseif ($call['profile'] === 'WooCommerce') {
            $normalizer = new WooCommerce();
            $normalizedVariants = $normalizer->normalizeVariants($products_result->fetchAll());

            foreach ($normalizedVariants as $product) {
                $this->migrateVariant($product);
            }
        }

        return $this->getProgress()->done();
    }

    /**
     * Helper function which gets the configurator groups for
     * a given product
     *
     * @param $productId
     *
     * @return array
     */
    public function getConfiguratorGroups($productId)
    {
        // get configurator groups for the given product
        $builder = Shopware()->Models()->createQueryBuilder();
        $builder->select(['PARTIAL article.{id}', 'configuratorSet', 'groups'])
                ->from(Article::class, 'article')
                ->innerJoin('article.configuratorSet', 'configuratorSet')
                ->leftJoin('configuratorSet.groups', 'groups')
                ->where('article.id = ?1')
                ->setParameter(1, $productId);

        $result = $builder->getQuery()->getArrayResult();
        $result = array_pop($result);

        $configuratorArray = $result['configuratorSet'];
        $groups = $configuratorArray['groups'];

        // Additionally get the options for the given configurator set
        // this relation seems not to be available in the configurator models
        // (the configuratorSet-Model returns all group's options, even those
        // not related to the given set)
        $sql = 'SELECT options.group_id, TRUE AS active, options.id FROM `s_article_configurator_sets` sets

	     LEFT JOIN s_article_configurator_set_option_relations relations
	     ON relations.set_id = sets.id

	     LEFT JOIN s_article_configurator_options options
	     ON options.id = relations.option_id

	     WHERE sets.id = ?';
        $results = Shopware()->Db()->fetchAll($sql, [$configuratorArray['id']]);

        // Sort the options by group
        $optionsByGroups = [];
        foreach ($results as $option) {
            $groupId = $option['group_id'];
            if (!isset($optionsByGroups[$groupId])) {
                $optionsByGroups[$groupId] = [];
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

    /**
     * @param array $product
     *
     * @return Progress
     */
    private function migrateVariant($product)
    {
        $id = $product['productID'];

        // continue if product was not imported before
        $productId = $this->getBaseArticleInfo($id);
        if (false === $productId) {
            return;
        }

        $groups = $this->getConfiguratorGroups($productId);

        $params = [
            'articleId' => $productId,
            'groups' => $groups,
        ];

        $this->increaseProgress();

        // Return the groups
        // The ExtJS frontend will care of the generation by triggering
        // the default article controller

        $this->getProgress()->addRequestParam('params', $params);
        $this->getProgress()->addRequestParam('create_variants', true);

        return $this->getProgress();
    }
}
