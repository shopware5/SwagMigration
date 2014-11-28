<?php
/*
 * (c) shopware AG <info@shopware.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Shopware SwagMigration Components - Category
 *
 * Category import adapter
 */
class Shopware_Components_Migration_Import_Resource_Category extends Shopware_Components_Migration_Import_Resource_Abstract
{

    /**
     * Returns the default error message for this import class
     * @return mixed
     */
    public function getDefaultErrorMessage()
    {
        if ($this->getInternalName() == 'import_categories') {
            return $this->getNameSpace()->get(
                'errorImportingCategories',
                "An error occurred while importing categories"
            );
        } elseif ($this->getInternalName() == 'import_article_categories') {
            return $this->getNameSpace()->get(
                'errorImportingArticleCategories',
                "An error assigning articles to categories"
            );
        }
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
        if ($this->getInternalName() == 'import_categories') {
            return sprintf(
                $this->getNameSpace()->get('progressCategories', "%s out of %s categories imported"),
                $progress->getOffset(),
                $progress->getCount()
            );
        } elseif ($this->getInternalName() == 'import_article_categories') {
            return sprintf(
                $this->getNameSpace()->get('progressArticleCategories', "%s out of %s articles assigned to categories"),
                $progress->getOffset(),
                $progress->getCount()
            );
        }
    }

    /**
     * Returns the default 'all done' message
     * @return mixed
     */
    public function getDoneMessage()
    {
        return $this->getNameSpace()->get('importedCategories', "Categories successfully imported!");
    }


    /**
     * Set a category target id
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

        Shopware()->Db()->query($sql, array(Shopware_Components_Migration::MAPPING_CATEGORY_TARGET, $id, $target));
    }

    /**
     * Get a category target id
     * @param $id
     * @return bool|string
     */
    public function getCategoryTarget($id)
    {
        if (!isset($id) || empty($id)) {
            return false;
        }
        return Shopware()->Db()->fetchOne(
            "SELECT `targetID` FROM `s_plugin_migrations` WHERE typeID=? AND sourceID=?",
            array(Shopware_Components_Migration::MAPPING_CATEGORY_TARGET, $id)
        );
    }

    /**
     * Get a category target id
     * @param $id
     * @return bool|string
     */
    public function getCategoryTargetLike($id)
    {
        if (!isset($id) || empty($id)) {
            return false;
        }
        return Shopware()->Db()->fetchOne(
            "SELECT `targetID` FROM `s_plugin_migrations` WHERE typeID=? AND sourceID LIKE ?",
            array(
                Shopware_Components_Migration::MAPPING_CATEGORY_TARGET,
                $id . Shopware_Components_Migration::CATEGORY_LANGUAGE_SEPARATOR . '%'
            )
        );
    }

    /**
     * Delete category target
     * @param $id
     */
    public function deleteCategoryTarget($id)
    {
        $sql = "DELETE FROM s_plugin_migrations WHERE typeID = ? AND sourceID = '{$id}'";
        Shopware()->Db()->query($sql, array(Shopware_Components_Migration::MAPPING_CATEGORY_TARGET));
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
     * - $this->increaseProgress() to increase the offset/progress by one
     * - $this->getProgress()->getOffset() to get the current progress' offset
     * - return $this->getProgress()->error("Message") in order to stop with an error message
     * - return $this->getProgress() in order to be called again with the current offset
     * - return $this->getProgress()->done() in order to mark the import as finished
     *
     * The category import adapter handles categories as well as article-category assignments.
     *
     * @return Shopware_Components_Migration_Import_Progress
     */
    public function run()
    {
        if ($this->getInternalName() == 'import_categories') {
            return $this->importCategories();
        } elseif ($this->getInternalName() == 'import_article_categories') {
            return $this->importArticleCategories();
        }
    }

    /**
     * Will import the actual categories
     *
     * @return $this|Shopware_Components_Migration_Import_Progress
     */
    public function importCategories()
    {
        $offset = $this->getProgress()->getOffset();

        $skip = false;

        // Cleanup previous category imports
        if (!$skip && $offset === 0) {
            Shopware()->Db()->query("DELETE FROM s_plugin_migrations WHERE typeID IN (?, ?);",
                array(Shopware_Components_Migration::MAPPING_CATEGORY_TARGET,2)
            );
        }

        $categories = $this->Source()->queryCategories($offset);
        $count = $categories->rowCount()+$offset;
        $this->getProgress()->setCount($count);
        $this->initTaskTimer();

        while (!$skip && $category = $categories->fetch()) {
            //check if the category split into the different translations
            if(!empty($category['languageID'])&& strpos($category['categoryID'], Shopware_Components_Migration::CATEGORY_LANGUAGE_SEPARATOR)===false) {
                $category['categoryID'] = $category['categoryID'] . Shopware_Components_Migration::CATEGORY_LANGUAGE_SEPARATOR . $category['languageID'];

                if(!empty($category['parentID'])) {
                    $category['parentID'] = $category['parentID'] . Shopware_Components_Migration::CATEGORY_LANGUAGE_SEPARATOR . $category['languageID'];
                }
            }

            $target_parent = $this->getCategoryTarget($category['parentID']);
            // More generous approach - will ignore languageIDs
            if (empty($target_parent) && !empty($category['parentID'])) {
                $target_parent = $this->getCategoryTargetLike($category['parentID']);
            }
            // Do not create empty categories
            if(empty($category['description'])) {
                $this->increaseProgress();
                continue;
            }

            if(!empty($category['parentID'])) {
                // Map the category IDs
                if (false !== $target_parent) {
                    $category['parent'] = $target_parent;
                } else {
                    if (empty($target_parent)) {
                        error_log("Parent category not found: {$category['parentID']}. Will not create '{$category['description']}'");
                        $this->increaseProgress();
                        continue;
                    }
                }
            } elseif( !empty($category['languageID'])
                && !empty($this->Request()->language)
                && !empty($this->Request()->language[$category['languageID']])
            ) {
                $sql = 'SELECT `category_id` FROM `s_core_shops` WHERE `locale_id`=?';
                $category['parent'] = Shopware()->Db()->fetchOne($sql , array($this->Request()->language[$category['languageID']]));
            }

            try {
                $category['targetID'] = Shopware()->Api()->Import()->sCategory($category);
                $this->setCategoryTarget($category['categoryID'], $category['targetID']);
            }
            catch(Exception $e) {
                echo "<pre>";
                print_r($e);
                echo "</pre>";
                exit();
            }

            $sql = '
                INSERT INTO `s_plugin_migrations` (`typeID`, `sourceID`, `targetID`)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE `targetID`=VALUES(`targetID`);
            ';

            Shopware()->Db()->query($sql , array(Shopware_Components_Migration::MAPPING_CATEGORY, $category['categoryID'], $category['targetID']));

            $this->increaseProgress();
            if ($this->newRequestNeeded()) {
                return $this->getProgress();
            }
        }

        $this->getProgress()->addRequestParam('import_article_categories', 1);
        return $this->getProgress()->done();
    }

    /**
     * Will assign articles to categories
     *
     * @return Shopware_Components_Migration_Import_Progress
     */
    public function importArticleCategories()
    {
        $offset = $this->getProgress()->getOffset();

        $result = $this->Source()->queryProductCategories($offset);

        $count = $result->rowCount()+$offset;
        $this->getProgress()->setCount($count);

        $taskStartTime  = $this->initTaskTimer();

        while ($productCategory = $result->fetch()) {
            if ($this->newRequestNeeded()) {
                return $this->getProgress();
            }
            $this->increaseProgress();

            $sql = '
                SELECT ad.articleID
                FROM s_plugin_migrations pm
                JOIN s_articles_details ad
                ON ad.id=pm.targetID
                WHERE `sourceID`=?
                AND `typeID`=?
            ';
            $article = Shopware()->Db()->fetchOne($sql , array($productCategory['productID'], Shopware_Components_Migration::MAPPING_ARTICLE));

            if(empty($article)) {
                continue;
            }

            $sql = '
                SELECT `targetID`
                FROM `s_plugin_migrations`
                WHERE `typeID`=? AND (`sourceID`=? OR `sourceID` LIKE ?)
            ';
            // Also take language categories into account
            $categories = Shopware()->Db()->fetchCol($sql , array(Shopware_Components_Migration::MAPPING_CATEGORY, $productCategory['categoryID'], $productCategory['categoryID'] . Shopware_Components_Migration::CATEGORY_LANGUAGE_SEPARATOR.'%'));

            if(empty($categories)) {
                continue;
            }

            foreach ($categories as $category) {
                Shopware()->Api()->Import()->sArticleCategory($article, $category, false);
            }
        }

        $this->getProgress()->done();

    }

}
