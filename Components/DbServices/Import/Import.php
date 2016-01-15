<?php
/**
 * Shopware 5
 * Copyright (c) shopware AG
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

namespace Shopware\SwagMigration\Components\DbServices\Import;

use Enlight_Components_Db_Adapter_Pdo_Mysql as PDOConnection;
use Shopware\Components\Logger;
use Shopware\Components\Model\ModelManager;
use Shopware_Components_Config as Config;
use Symfony\Component\DependencyInjection\Container;

class Import
{
    /** @var Container $container */
    private $container;

    /* @var PDOConnection $db */
    private $db = null;

    /* @var ModelManager $em */
    private $em = null;

    /** @var Logger $logger */
    private $logger;

    /** @var Config $config */
    private $config;

    /* @var ArticleImporter $articleImporter */
    private $articleImporter = null;

    /* @var CategoryImporter $categoryImporter */
    private $categoryImporter = null;

    /* @var CustomerImporter $customerImporter */
    private $customerImporter = null;

    /* @var ImageImporter $imageImporter */
    private $imageImporter = null;

    /* @var PriceImporter $priceImporter */
    private $priceImporter = null;

    /* @var TranslationImporter $translationImporter */
    private $translationImporter = null;

    /**
     * Import constructor.
     *
     * @param Container $container
     */
    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->db = $this->container->get('db');
        $this->em = $this->container->get('models');
        $this->logger = $this->container->get('pluginlogger');
        $this->config = $this->container->get('config');
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
    public function setArticleConfigurationData(array $article)
    {
        $this->getArticleImporter()->setConfiguratorData($article);
    }

    /**
     * @param array $article
     * @return bool|string
     */
    public function setArticlePriceData(array $article)
    {
        $articlePricesId = $this->getArticleImporter()->setPriceData($article);

        return $articlePricesId;
    }

    /**
     * @param array $article
     */
    public function deleteArticleLinks(array $article)
    {
        $this->getArticleImporter()->deleteArticleLinks($article);
    }

    /**
     * @param array $linkData
     * @return bool|string
     */
    public function addArticleLink(array $linkData)
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
    public function customer(array $customer)
    {
        $customerData = $this->getCustomerImporter()->import($customer);

        return $customerData;
    }

    /**
     * @param array $image
     * @return int
     */
    public function articleImage(array $image)
    {
        $imageId = $this->getImageImporter()->importArticleImage($image);

        return $imageId;
    }

    /**
     * @param array $price
     * @return bool|int
     */
    public function articlePrice(array $price)
    {
        $priceId = $this->getPriceImporter()->importArticlePrice($price);

        return $priceId;
    }

    /**
     * @param string $type
     * @param string $objectKey
     * @param string $language
     * @param array $translation
     * @return bool|int
     */
    public function translation($type, $objectKey, $language, array $translation)
    {
        $translationId = $this->getTranslationImporter()->import($type, $objectKey, $language, $translation);

        return $translationId;
    }
}
