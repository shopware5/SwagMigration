<?php
/**
 * (c) shopware AG <info@shopware.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Shopware\SwagMigration\Components\DbServices\Import;

use Enlight_Components_Db_Adapter_Pdo_Mysql as PDOConnection;
use Shopware\Components\Logger;
use Shopware\Components\Model\ModelManager;
use Shopware\Models\Category\Category;
use Shopware\Models\Category\Repository as CategoryRepository;

class CategoryImporter
{
    /**
     * @var PDOConnection
     */
    private $db;

    /**
     * @var ModelManager
     */
    private $em;

    /**
     * @var CategoryRepository
     */
    private $repository;

    /**
     * @var Logger
     */
    private $logger;

    /**
     * CategoryImporter constructor.
     *
     * @param PDOConnection $db
     * @param ModelManager  $em
     * @param Logger        $logger
     */
    public function __construct(PDOConnection $db, ModelManager $em, Logger $logger)
    {
        $this->db = $db;
        $this->em = $em;
        $this->repository = $this->em->getRepository(Category::class);
        $this->logger = $logger;
    }

    /**
     * @param array $category
     *
     * @return bool|int
     */
    public function import(array $category)
    {
        $category = $this->prepareCategoryData($category);

        // Try to find an existing category by name and parent
        $model = null;
        if (isset($category['parent']) && isset($category['name'])) {
            $model = $this->repository->findOneBy(['parent' => $category['parent'], 'name' => $category['name']]);
        }

        if (!$model instanceof Category) {
            $model = new Category();
        }

        $parentModel = null;
        if (isset($category['parent'])) {
            $parentModel = $this->repository->find((int) $category['parent']);
            if (!$parentModel instanceof Category) {
                $this->logger->error("Parent category {$category['parent']} not found!");

                return false;
            }
            unset($category['parent']);
        }

        $model->fromArray($category);
        $model->setParent($parentModel);

        $this->em->persist($model);
        $this->em->flush();

        // Set category attributes
        $attributes = $this->prepareCategoryAttributesData($category);
        unset($category);

        $categoryId = $model->getId();
        if (!empty($attributes)) {
            $attributeID = $this->db->fetchOne(
                'SELECT id FROM s_categories_attributes WHERE categoryID = ?',
                [$categoryId]
            );
            if ($attributeID === false) {
                $attributes['categoryID'] = $categoryId;
                $this->db->insert('s_categories_attributes', $attributes);
            } else {
                $this->db->update(
                    's_categories_attributes',
                    $attributes,
                    ['categoryID = ?' => $categoryId]
                );
            }
        }

        return $categoryId;
    }

    /**
     * @param int $articleId
     * @param int $categoryId
     */
    public function assignArticlesToCategory($articleId, $categoryId)
    {
        $categoryId = (int) $categoryId;
        $articleId = (int) $articleId;
        if (empty($categoryId) || empty($articleId)) {
            return;
        }

        $sql = "INSERT IGNORE INTO s_articles_categories (articleID, categoryID)
                SELECT {$articleId} as articleID, c.id as categoryID
                FROM s_categories c
                WHERE c.id IN ({$categoryId})";

        if ($this->db->query($sql) === false) {
            return;
        }

        $categoryDenormalization = Shopware()->Container()->get('categorydenormalization');
        $categoryDenormalization->addAssignment($articleId, $categoryId);
        $categoryDenormalization->disableTransactions();
    }

    /**
     * @param array $category
     *
     * @return array
     */
    private function prepareCategoryData(array $category)
    {
        // In order to be compatible with the old API syntax but to also be able to use ->fromArray(),
        // we map from the old keys to doctrine keys
        $mappings = [
            'description' => 'name',
            'cmsheadline' => 'cmsHeadline',
            'metakeywords' => 'metaKeywords',
            'metadescription' => 'metaDescription',
        ];

        foreach ($mappings as $original => $new) {
            if (isset($category[$original])) {
                $category[$new] = $category[$original];
                unset($category[$original]);
            }
        }

        return $category;
    }

    /**
     * @param array $category
     *
     * @return array
     */
    private function prepareCategoryAttributesData(array $category)
    {
        $attributes = [];
        for ($i = 1; $i <= 6; ++$i) {
            if (isset($category['ac_attr' . $i])) {
                $attributes['attribute' . $i] = (string) $category['ac_attr' . $i];
            } elseif (isset($category['attr'][$i])) {
                $attributes['attribute' . $i] = (string) $category['attr'][$i];
            }
        }

        return $attributes;
    }
}
