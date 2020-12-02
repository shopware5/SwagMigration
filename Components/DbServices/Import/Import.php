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
use Shopware_Components_Config as Config;
use Symfony\Component\DependencyInjection\Container;

class Import
{
    /**
     * @var Container
     */
    private $container;

    /**
     * @var PDOConnection
     */
    private $db;

    /**
     * @var ModelManager
     */
    private $em;

    /**
     * @var Logger
     */
    private $logger;

    /**
     * @var Config
     */
    private $config;

    /**
     * @var ArticleImporter
     */
    private $articleImporter;

    /**
     * @var CategoryImporter
     */
    private $categoryImporter;

    /**
     * @var CustomerImporter
     */
    private $customerImporter;

    /**
     * @var ImageImporter
     */
    private $imageImporter;

    /**
     * @var PriceImporter
     */
    private $priceImporter;

    /**
     * @var TranslationImporter
     */
    private $translationImporter;

    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->db = $this->container->get('db');
        $this->em = $this->container->get('models');
        $this->logger = $this->container->get('pluginlogger');
        $this->config = $this->container->get('config');
        $this->db->exec("SET NAMES 'utf8' COLLATE 'utf8_general_ci'");
    }

    /**
     * Create categories
     *
     * @return int
     */
    public function category(array $category)
    {
        return $this->getCategoryImporter()->import($category);
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
     * @return array|bool
     */
    public function article(array $article)
    {
        $article = $this->getArticleImporter()->import($article);

        return $article;
    }

    /**
     * Create a new style configurator from a simple variant
     */
    public function setArticleConfigurationData(array $article)
    {
        $this->getArticleImporter()->setConfiguratorData($article);
    }

    /**
     * @return bool|string
     */
    public function setArticlePriceData(array $article)
    {
        return $this->getArticleImporter()->setPriceData($article);
    }

    public function deleteArticleLinks(array $article)
    {
        $this->getArticleImporter()->deleteArticleLinks($article);
    }

    /**
     * @return bool|string
     */
    public function addArticleLink(array $linkData)
    {
        return $this->getArticleImporter()->addArticleLink($linkData);
    }

    /**
     * @param int|string $articleId
     */
    public function deleteArticleImages($articleId)
    {
        $articleId = (int) $articleId;

        $this->getArticleImporter()->deleteImages($articleId);
    }

    /**
     * @return array
     */
    public function customer(array $customer)
    {
        return $this->getCustomerImporter()->import($customer);
    }

    /**
     * @return int
     */
    public function articleImage(array $image)
    {
        return $this->getImageImporter()->importArticleImage($image);
    }

    /**
     * @return bool|int
     */
    public function articlePrice(array $price)
    {
        return $this->getPriceImporter()->importArticlePrice($price);
    }

    /**
     * @param string $type
     * @param string $objectKey
     * @param string $language
     *
     * @return bool|int
     */
    public function translation($type, $objectKey, $language, array $translation)
    {
        $translationId = $this->getTranslationImporter()->import($type, $objectKey, $language, $translation);

        return $translationId;
    }

    /**
     * @return ArticleImporter
     */
    private function getArticleImporter()
    {
        if ($this->articleImporter === null) {
            $this->articleImporter = new ArticleImporter($this->db, $this->em, $this->logger);
        }

        return $this->articleImporter;
    }

    /**
     * @return CategoryImporter
     */
    private function getCategoryImporter()
    {
        if ($this->categoryImporter === null) {
            $this->categoryImporter = new CategoryImporter($this->db, $this->em, $this->logger);
        }

        return $this->categoryImporter;
    }

    /**
     * @return CustomerImporter
     */
    private function getCustomerImporter()
    {
        if ($this->customerImporter === null) {
            $this->customerImporter = new CustomerImporter($this->db, $this->em, $this->config);
        }

        return $this->customerImporter;
    }

    /**
     * @return ImageImporter
     */
    private function getImageImporter()
    {
        if ($this->imageImporter === null) {
            $this->imageImporter = new ImageImporter($this->db, $this->em, $this->logger);
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
}
