<?php

namespace Shopware\SwagMigration\Components\DbServices\Import;

use \Shopware\Components\Model\ModelManager;
use \Shopware\Models\Category\Category;
use \Shopware\Models\Category\Repository as CategoryRepository;

class CategoryImporter
{
    /** @var \Enlight_Components_Db_Adapter_Pdo_Mysql */
    private $db = null;

    /** @var ModelManager */
    private $em = null;

    /** @var CategoryRepository */
    private $repository = null;

    /* @var \Shopware\Components\Logger */
    private $logger;

    public function __construct(\Enlight_Components_Db_Adapter_Pdo_Mysql $db, ModelManager $em)
    {
        $this->db = $db;
        $this->em = $em;
        $this->logger = Shopware()->PluginLogger();
        $this->repository = $this->em->getRepository('Shopware\Models\Category\Category');
    }

    public function import($category)
    {
        $category = $this->prepareCategoryData($category);

        // Try to find an existing category by name and parent
        if (isset($category['parent']) && isset($category['name']))
            $model = $this->repository->findOneBy(array('parent' => $category['parent'], 'name' => $category['name']));

        if (!$model instanceof Category)
            $model = new Category();

        if (isset($category['parent'])) {
            $parentModel = $this->repository->find((int) $category['parent']);
            if (!$parentModel instanceof Category) {
                $this->logger->error("Parent category {$category['parent']} not found!");
                return false;
            }
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
            $attributeID = $this->db->fetchOne("SELECT id FROM s_categories_attributes WHERE categoryID = ?", array($categoryId));
            if ($attributeID === false) {
                $attributes['categoryID'] = $categoryId;
                $this->db->insert('s_categories_attributes', $attributes);
            } else {
                $this->db->update('s_categories_attributes',
                    $attributes,
                    array('categoryID = ?' => $categoryId)
                );
            }
        }

        return $categoryId;
    }

    /**
     * @param array $category
     * @return array
     */
    private function prepareCategoryData($category)
    {
        // In order to be compatible with the old API syntax but to also be able to use ->fromArray(),
        // we map from the old keys to doctrine keys
        $mappings = array(
            'description' => 'name',
            'cmsheadline' => 'cmsHeadline',
            'metakeywords' => 'metaKeywords',
            'metadescription' => 'metaDescription'
        );

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
     * @return array
     */
    private function prepareCategoryAttributesData($category)
    {
        $attributes = array();
        for ($i = 1; $i <= 6; $i++) {
            if (isset($category['ac_attr'.$i]))
                $attributes['attribute'.$i] = (string) $category['ac_attr'.$i];
            elseif (isset($category['attr'][$i]))
                $attributes['attribute'.$i] = (string) $category['attr'][$i];
        }

        return $attributes;
    }

    /**
     * @param int $articleId
     * @param int $categoryId
     */
    public function assignArticlesToCategory($articleId, $categoryId)
    {
        $categoryId = intval($categoryId);
        $articleId = intval($articleId);
        if (empty($categoryId) || empty($articleId))
            return;

        $sql = "
            INSERT IGNORE INTO s_articles_categories (articleID, categoryID)

            SELECT $articleId as articleID, c.id as categoryID
            FROM s_categories c
            WHERE c.id IN ($categoryId)
        ";

        if ($this->db->query($sql) === false)
            return;

        Shopware()->CategoryDenormalization()->addAssignment($articleId, $categoryId);
        Shopware()->CategoryDenormalization()->disableTransactions();
    }
}