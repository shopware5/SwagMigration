<?php
/**
 * (c) shopware AG <info@shopware.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Shopware\SwagMigration\Components\Migration\Import\Resource;

use Shopware;
use Shopware\SwagMigration\Components\Migration\Import\Progress;
use Shopware\SwagMigration\Components\Normalizer\WooCommerce;

class Configurator extends AbstractResource
{
    /**
     * {@inheritdoc}
     */
    public function getDefaultErrorMessage()
    {
        return $this->getNameSpace()->get(
            'errorGeneratingVariantsFromAttributes',
            'An error occurred while generating configurators'
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getCurrentProgressMessage(Progress $progress)
    {
        return \sprintf(
            $this->getNameSpace()->get('configuratorProgress', '%s out of %s configurators imported'),
            $this->getProgress()->getOffset(),
            $this->getProgress()->getCount()
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getDoneMessage()
    {
        return $this->getNameSpace()->get('generatedConfigurators', 'Configurators successfully generated!');
    }

    /**
     * {@inheritdoc}
     */
    public function run()
    {
        $offset = $this->getProgress()->getOffset();
        $call = \array_merge($this->Request()->getPost(), $this->Request()->getQuery());

        $products_result = $this->Source()->queryAttributedProducts($offset);
        if (empty($products_result)) {
            $this->getProgress()->addRequestParam('import_generate_variants', null);
            $this->getProgress()->addRequestParam('import_create_configurator_variants', null);

            return $this->getProgress()->done();
        }

        $count = $products_result->rowCount() + $offset;
        $this->getProgress()->setCount($count);
        $this->initTaskTimer();

        if ($call['profile'] !== 'WooCommerce') {
            while ($product = $products_result->fetch()) {
                $this->migrateConfigurator($product);
            }
        } elseif ($call['profile'] === 'WooCommerce') {
            $normalizer = new WooCommerce();
            $normalizedVariants = $normalizer->normalizeVariants($products_result->fetchAll());

            foreach ($normalizedVariants as $product) {
                $this->migrateConfigurator($product);
            }
        }

        // Set variant generation to be the next step
        $this->getProgress()->addRequestParam('import_create_configurator_variants', 1);

        return $this->getProgress()->done();
    }

    /**
     * @param array $product
     *
     * @return Progress
     */
    private function migrateConfigurator($product)
    {
        $id = $product['productID'];

        // Skip products which have not been imported before
        $productId = $this->getBaseArticleInfo($id);
        if ($productId === false) {
            return;
        }

        // Create configurator set for product
        $configuratorSetName = 'Generated Set - ' . $id;
        $configuratorSetId = Shopware()->Db()->fetchOne(
            "
                SELECT `id`
                FROM `s_article_configurator_sets`
                WHERE `name`='{$configuratorSetName}' LIMIT 1"
        );
        if ($configuratorSetId === false) {
            $sql = "INSERT INTO s_article_configurator_sets SET `name`='{$configuratorSetName}'";
            Shopware()->Db()->query($sql);
            $configuratorSetId = Shopware()->Db()->lastInsertId();
        }

        // Get all attributes of the current product
        $result = $this->Source()->queryProductAttributes($id);

        $options = [];
        $groups = [];

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
                $sql = "INSERT INTO `s_article_configurator_price_variations` (`configurator_set_id`, `options`, `variation`) VALUES ({$configuratorSetId}, CONCAT('|', {$optionId}, '|'), {$price})";
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
}
