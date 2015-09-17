<?php

namespace Shopware\SwagMigration\Components\DbServices\Import;

class Import
{
    /* @var \Enlight_Components_Db_Adapter_Pdo_Mysql */
    private $db = null;

    /* @var \Shopware\Components\Model\ModelManager */
    private $em = null;

    /* @var ArticleImporter */
    private $articleImporter = null;

    /* @var CategoryImporter */
    private $categoryImporter = null;

    /* @var CustomerImporter */
    private $customerImporter = null;

    /* @var ImageImporter */
    private $imageImporter = null;

    /* @var PriceImporter */
    private $priceImporter = null;

    /* @var TranslationImporter */
    private $translationImporter = null;

    public function __construct()
    {
        $this->db = Shopware()->Container()->get('db');
        $this->em = Shopware()->Container()->get('models');
    }

    /**
     * @return ArticleImporter
     */
    private function getArticleImporter()
    {
        if ($this->articleImporter === null) {
            $this->articleImporter = new ArticleImporter($this->db, $this->em);
        }

        return $this->articleImporter;
    }

    /**
     * @return CategoryImporter
     */
    private function getCategoryImporter()
    {
        if ($this->categoryImporter === null) {
            $this->categoryImporter = new CategoryImporter($this->db, $this->em);
        }

        return $this->categoryImporter;
    }

    /**
     * @return CustomerImporter
     */
    private function getCustomerImporter()
    {
        if ($this->customerImporter === null) {
            $this->customerImporter = new CustomerImporter($this->db, $this->em);
        }

        return $this->customerImporter;
    }

    /**
     * @return ImageImporter
     */
    private function getImageImporter()
    {
        if ($this->imageImporter === null) {
            $this->imageImporter = new ImageImporter($this->db, $this->em);
        }

        return $this->imageImporter;
    }

    /**
     * @return PriceImporter
     */
    private function getPriceImporter()
    {
        if ($this->priceImporter === null) {
            $this->priceImporter = new PriceImporter($this->db);
        }

        return $this->priceImporter;
    }

    /**
     * @return TranslationImporter
     */
    private function getTranslationImporter()
    {
        if ($this->translationImporter === null) {
            $this->translationImporter = new TranslationImporter($this->db);
        }

        return $this->translationImporter;
    }

    /**
     * Create categories
     *
     * @param array $category
     * @return int
     */
    public function category(array $category)
    {
        $categoryId = $this->getCategoryImporter()->import($category);

        return $categoryId;
    }

    /**
     * @param int $articleId
     * @param int $categoryId
     */
    public function articleCategory($articleId, $categoryId)
    {
        $this->getCategoryImporter()->assignArticlesToCategory($articleId, $categoryId);
    }

    /**
     * Create products
     *
     * @param array $article
     * @return array|bool
     */
    public function article(array $article)
    {
        $article = $this->getArticleImporter()->import($article);

        return $article;
    }

    /**
     * Create a new style configurator from a simple variant
     *
     * @param array $article
     */
    public function setArticleConfigurationData($article)
    {
        $this->getArticleImporter()->setConfiguratorData($article);
    }

    /**
     * @param array $article
     * @return bool|string
     */
    public function setArticlePriceData($article)
    {
        $articlePricesId = $this->getArticleImporter()->setPriceData($article);

        return $articlePricesId;
    }

    /**
     * @param array $article
     */
    public function deleteArticleLinks($article)
    {
        $this->getArticleImporter()->deleteArticleLinks($article);
    }

    /**
     * @param array $linkData
     * @return bool|string
     */
    public function addArticleLink($linkData)
    {
        $linkId = $this->getArticleImporter()->addArticleLink($linkData);

        return $linkId;
    }

    /**
     * @param int|string $articleId
     */
    public function deleteArticleImages($articleId)
    {
        $articleId = intval($articleId);

        $this->getArticleImporter()->deleteImages($articleId);
    }

    /**
     * @param array $customer
     * @return array
     */
    public function customer($customer)
    {
        $customerData = $this->getCustomerImporter()->import($customer);

        return $customerData;
    }

    /**
     * @param array $image
     * @return int
     */
    public function articleImage($image)
    {
        $imageId = $this->getImageImporter()->importArticleImage($image);

        return $imageId;
    }

    /**
     * @param array $price
     * @return bool|int
     */
    public function articlePrice($price)
    {
        $priceId = $this->getPriceImporter()->importArticlePrice($price);

        return $priceId;
    }

    public function translation($type, $objectKey, $language, $translation)
    {
        $translationId = $this->getTranslationImporter()->import($type, $objectKey, $language, $translation);

        return $translationId;
    }
}