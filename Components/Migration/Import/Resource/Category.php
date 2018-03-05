<?php
/**
 * (c) shopware AG <info@shopware.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Shopware\SwagMigration\Components\Migration\Import\Resource;

use Exception;
use Shopware\SwagMigration\Components\DbServices\Import\Import;
use Shopware\SwagMigration\Components\Migration;
use Shopware\SwagMigration\Components\Migration\Import\Progress;

/**
 * Shopware SwagMigration Components - Category
 *
 * Category import adapter
 *
 * @category  Shopware
 *
 * @copyright Copyright (c) 2012, shopware AG (http://www.shopware.de)
 */
class Category extends AbstractResource
{
    /**
     * @var \Enlight_Components_Db_Adapter_Pdo_Mysql
     */
    private $db;

    /** @var array */
    private $unmapped = [];

    /**
     * @throws Exception
     *
     * @return \Enlight_Components_Db_Adapter_Pdo_Mysql
     */
    public function getDb()
    {
        if ($this->db === null) {
            $this->db = Shopware()->Container()->get('db');
        }

        return $this->db;
    }

    /**
     * {@inheritdoc}
     */
    public function getDefaultErrorMessage()
    {
        if ($this->getInternalName() == 'import_categories') {
            return $this->getNameSpace()->get(
                'errorImportingCategories',
                'An error occurred while importing categories'
            );
        }

        if ($this->getInternalName() == 'import_article_categories') {
            return $this->getNameSpace()->get(
                'errorImportingArticleCategories',
                'An error assigning articles to categories'
            );
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getCurrentProgressMessage(Progress $progress)
    {
        if ($this->getInternalName() == 'import_categories') {
            return sprintf(
                $this->getNameSpace()->get('progressCategories', '%s out of %s categories imported'),
                $progress->getOffset(),
                $progress->getCount()
            );
        }

        if ($this->getInternalName() == 'import_article_categories') {
            return sprintf(
                $this->getNameSpace()->get('progressArticleCategories', '%s out of %s articles assigned to categories'),
                $progress->getOffset(),
                $progress->getCount()
            );
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getDoneMessage()
    {
        return $this->getNameSpace()->get('importedCategories', 'Categories successfully imported!');
    }

    /**
     * Set a category target id
     *
     * @param $id
     * @param $target
     */
    public function setCategoryTarget($id, $target)
    {
        $this->deleteCategoryTarget($id);

        $sql = '
            INSERT INTO `s_plugin_migrations` (`typeID`, `sourceID`, `targetID`)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE `targetID`=VALUES(`targetID`);
        ';

        $this->getDb()->query($sql, [Migration::MAPPING_CATEGORY_TARGET, $id, $target]);
    }

    /**
     * Get a category target id
     *
     * @param $id
     *
     * @return bool|string
     */
    public function getCategoryTarget($id)
    {
        if (!isset($id) || empty($id)) {
            return false;
        }

        return $this->getDb()->fetchOne(
            'SELECT `targetID` FROM `s_plugin_migrations` WHERE typeID=? AND sourceID=?',
            [Migration::MAPPING_CATEGORY_TARGET, $id]
        );
    }

    /**
     * Get a category target id
     *
     * @param $id
     *
     * @return bool|string
     */
    public function getCategoryTargetLike($id)
    {
        if (!isset($id) || empty($id)) {
            return false;
        }

        return $this->getDb()->fetchOne(
            'SELECT `targetID` FROM `s_plugin_migrations` WHERE typeID=? AND sourceID LIKE ?',
            [
                Migration::MAPPING_CATEGORY_TARGET,
                '%' . $id . '%',
            ]
        );
    }

    /**
     * Delete category target
     *
     * @param $id
     */
    public function deleteCategoryTarget($id)
    {
        $sql = "DELETE FROM s_plugin_migrations WHERE typeID = ? AND sourceID = '{$id}'";
        $this->getDb()->query($sql, [Migration::MAPPING_CATEGORY_TARGET]);
    }

    /**
     * The category import adapter handles categories as well as article-category assignments.
     *
     * {@inheritdoc}
     */
    public function run()
    {
        if ($this->getInternalName() == 'import_categories') {
            return $this->importCategories();
        }

        if ($this->getInternalName() == 'import_article_categories') {
            return $this->importArticleCategories();
        }
    }

    /**
     * Will import the actual categories
     *
     * @return $this|Progress
     */
    public function importCategories()
    {
        $call = array_merge($this->Request()->getPost(), $this->Request()->getQuery());
        $offset = $this->getProgress()->getOffset();

        $skip = false;

        // Cleanup previous category imports
        if (!$skip && $offset === 0) {
            $this->getDb()->query(
                'DELETE FROM s_plugin_migrations WHERE typeID IN (?, ?);',
                [Migration::MAPPING_CATEGORY_TARGET, 2]
            );
        }

        if ($call['profile'] === 'WooCommerce') {
            $locale = $this->Source()->getNormalizedLanguages();

            $sql = 'SELECT id FROM s_core_locales WHERE locale = ?';
            $languageId = $this->getDb()->fetchOne($sql, [$locale[0]]);
        }

        $categories = $this->Source()->queryCategories($offset);

        if (empty($categories)) {
            return $this->getProgress()->done();
        }

        $count = $categories->rowCount() + $offset;
        $this->getProgress()->setCount($count);
        $this->initTaskTimer();

        while (!$skip && $category = $categories->fetch()) {
            if ($call['profile'] === 'WooCommerce') {
                $category['languageID'] = $languageId;
            }

            //check if the category split into the different translations
            if (!empty($category['languageID'])
                && strpos($category['categoryID'], Migration::CATEGORY_LANGUAGE_SEPARATOR) === false
            ) {
                $category['categoryID'] = $category['categoryID'] . Migration::CATEGORY_LANGUAGE_SEPARATOR . $category['languageID'];

                if (!empty($category['parentID'])) {
                    $category['parentID'] = $category['parentID'] . Migration::CATEGORY_LANGUAGE_SEPARATOR . $category['languageID'];
                }
            }

            $target_parent = $this->getCategoryTarget($category['parentID']);

            // More generous approach - will ignore languageIDs
            if (empty($target_parent) && !empty($category['parentID'])) {
                $target_parent = $this->getCategoryTargetLike($category['parentID']);
            }

            // Do not create empty categories
            if (empty($category['description'])) {
                $this->increaseProgress();
                continue;
            }

            if (!empty($category['parentID'])) {
                // Map the category IDs
                if (false !== $target_parent) {
                    $category['parent'] = $target_parent;
                } else {
                    if (empty($target_parent)) {
                        Shopware()->PluginLogger()->error("Order '{$category['description']}' was not imported because the parent category was not found. The plugin tries to create it later.");
                        $this->unmapped[] = $category;
                        continue;
                    }
                }
            } elseif (!empty($category['languageID'])
                && !empty($this->Request()->language)
                && !empty($this->Request()->language[$category['languageID']])
            ) {
                $sql = 'SELECT `category_id` FROM `s_core_shops` WHERE `id`=?';
                $category['parent'] = $this->getDb()->fetchOne($sql, [$this->Request()->language[$category['languageID']]]);
            } else {
                $sql = 'SELECT `category_id` FROM `s_core_shops` WHERE `id`=?';
                $category['parent'] = $this->getDb()->fetchOne($sql, [$category['languageID']]);
            }

            try {
                /* @var Import $import */
                $import = Shopware()->Container()->get('swagmigration.import');
                $category['targetID'] = $import->category($category);

                $this->setCategoryTarget($category['categoryID'], $category['targetID']);
                // if meta_title isset update the Category
                if (!empty($category['meta_title'])) {
                    $this->getDb()->update(
                        's_categories',
                        ['meta_title' => $category['meta_title']],
                        ['id=?' => $category['targetID']]
                    );
                }
            } catch (Exception $e) {
                var_dump($e->getMessage());
                Shopware()->PluginLogger()->error("Category '{$category['description']}' was not imported.");
                $this->increaseProgress();
                exit();
            }

            $sql = '
                INSERT INTO `s_plugin_migrations` (`typeID`, `sourceID`, `targetID`)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE `targetID`=VALUES(`targetID`);
            ';

            $this->getDb()->query($sql, [Migration::MAPPING_CATEGORY, $category['categoryID'], $category['targetID']]);

            $this->increaseProgress();
            if ($this->newRequestNeeded()) {
                return $this->getProgress();
            }
        }

        if (count($this->unmapped) > 0) {
            $this->importCategoriesWithoutParents();
            if ($this->newRequestNeeded()) {
                return $this->getProgress();
            }
        }

        $this->getProgress()->addRequestParam('import_article_categories', 1);
        $this->unmapped = [];

        return $this->getProgress()->done();
    }

    /**
     * Will assign articles to categories
     *
     * @return Progress
     */
    public function importArticleCategories()
    {
        $offset = $this->getProgress()->getOffset();

        $result = $this->Source()->queryProductCategories($offset);

        if (empty($result)) {
            return $this->getProgress()->done();
        }

        $count = $result->rowCount() + $offset;
        $this->getProgress()->setCount($count);

        $this->initTaskTimer();

        /* @var Import $import */
        $import = Shopware()->Container()->get('swagmigration.import');

        while ($productCategory = $result->fetch()) {
            if ($this->newRequestNeeded()) {
                return $this->getProgress();
            }
            $this->increaseProgress();

            $sql = '
                SELECT ad.articleID
                FROM s_plugin_migrations pm
                JOIN s_articles_details ad ON ad.id = pm.targetID
                WHERE sourceID = ? AND (typeID = ? OR typeID = ?)
            ';

            $article = $this->getDb()->fetchOne(
                $sql,
                [
                    $productCategory['productID'],
                    Migration::MAPPING_ARTICLE,
                    Migration::MAPPING_VALID_NUMBER,
                ]
            );

            if (empty($article)) {
                continue;
            }

            $sql = '
                SELECT `targetID`
                FROM `s_plugin_migrations`
                WHERE `typeID`=? AND (`sourceID`=? OR `sourceID` LIKE ?)
            ';
            // Also take language categories into account
            $categories = $this->getDb()->fetchCol(
                $sql,
                [
                    Migration::MAPPING_CATEGORY,
                    $productCategory['categoryID'],
                    $productCategory['categoryID'] . Migration::CATEGORY_LANGUAGE_SEPARATOR . '%',
                ]
            );

            if (empty($categories)) {
                continue;
            }

            foreach ($categories as $category) {
                $import->articleCategory($article, $category);
            }
        }

        $this->getProgress()->done();
    }

    /**
     * If there were unmapped categories because the parent does not exist at the time,
     * they were imported here in a second step.
     *
     * @return bool
     */
    private function importCategoriesWithoutParents()
    {
        foreach ($this->unmapped as $key => $category) {
            $target_parent = $this->getCategoryTarget($category['parentID']);

            // More generous approach - will ignore languageIDs
            if (empty($target_parent) && !empty($category['parentID'])) {
                $target_parent = $this->getCategoryTargetLike($category['parentID']);
            }

            if (false !== $target_parent) {
                $category['parent'] = $target_parent;
            } else {
                continue;
            }

            try {
                /* @var Import $import */
                $import = Shopware()->Container()->get('swagmigration.import');
                $category['targetID'] = $import->category($category);

                $this->setCategoryTarget($category['categoryID'], $category['targetID']);
                // if meta_title isset update the Category
                if (!empty($category['meta_title'])) {
                    $this->getDb()->update(
                        's_categories',
                        ['meta_title' => $category['meta_title']],
                        ['id=?' => $category['targetID']]
                    );
                }
                unset($this->unmapped[$key]);
            } catch (Exception $e) {
                var_dump($e->getMessage());
                Shopware()->PluginLogger()->error("Category '{$category['description']}' was not imported.");
                $this->increaseProgress();
                exit();
            }
        }

        if (count($this->unmapped) > 0) {
            $this->importCategoriesWithoutParents();
            if ($this->newRequestNeeded()) {
                return $this->getProgress();
            }
        } else {
            return true;
        }
    }
}
