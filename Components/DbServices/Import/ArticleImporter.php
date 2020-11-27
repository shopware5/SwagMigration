<?php
/**
 * (c) shopware AG <info@shopware.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Shopware\SwagMigration\Components\DbServices\Import;

use Doctrine\ORM\ORMException;
use Enlight_Components_Db_Adapter_Pdo_Mysql as PDOConnection;
use Shopware\Components\Logger;
use Shopware\Components\Model\ModelManager;
use Shopware\Models\Article\Article;
use Shopware\Models\Article\Repository as ArticleRepository;
use Shopware\Models\Media\Media;
use Shopware\Models\Media\Repository as MediaRepository;

class ArticleImporter
{
    /** @var array $articleFields */
    private $articleFields = [
        'supplierID',
        'name',
        'description',
        'description_long',
        'shippingtime',
        'datum',
        'active',
        'taxID',
        'pseudosales',
        'topseller',
        'keywords',
        'changetime',
        'pricegroupID',
        'filtergroupID',
        'crossbundlelook',
        'notification',
        'template',
        'mode',
        'main_detail_id',
        'available_from',
        'available_to',
        'configurator_set_id',
    ];

    /** @var array $articleDetailFields */
    private $articleDetailFields = [
        'articleID',
        'ordernumber',
        'suppliernumber',
        'kind',
        'additionaltext',
        'impressions',
        'sales',
        'active',
        'instock',
        'laststock',
        'stockmin',
        'weight',
        'position',
        'width',
        'height',
        'length',
        'ean',
        'unitID',
        'purchasesteps',
        'maxpurchase',
        'minpurchase',
        'purchaseunit',
        'referenceunit',
        'packunit',
        'releasedate',
        'shippingfree',
        'shippingtime',
    ];

    /**
     * @var ArticleRepository
     */
    private $articleRepository;

    /**
     * @var MediaRepository
     */
    private $mediaRepository;

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
     * */
    private $logger;

    /**
     * ArticleImporter constructor.
     *
     * @param PDOConnection $db
     * @param ModelManager  $em
     * @param Logger        $logger
     */
    public function __construct(PDOConnection $db, ModelManager $em, Logger $logger)
    {
        $this->em = $em;
        $this->db = $db;
        $this->logger = $logger;
    }

    /**
     * @param array $article
     *
     * @return array|bool
     */
    public function import(array $article)
    {
        // OrderNumber is required. Create a new one if its empty
        if (empty($article['ordernumber'])) {
            $prefix = Shopware()->Config()->backendAutoOrderNumberPrefix;

            $sql = "SELECT number FROM s_order_number WHERE name = 'articleordernumber'";
            $number = Shopware()->Db()->fetchOne($sql);

            if (!empty($number)) {
                do {
                    ++$number;

                    $sql = 'SELECT id FROM s_articles_details WHERE ordernumber LIKE ?';
                    $hit = Shopware()->Db()->fetchOne($sql, $prefix . $number);
                } while ($hit);
            }

            $article['ordernumber'] = $prefix . $number;

            $this->logger->warning('Order number was not given! The System created a new one!');
        }

        $article = $this->prepareArticleData($article);
        $article = $this->prepareArticleDetailData($article);
        $article = $this->prepareArticleAttributesData($article);

        $article = $this->findExistingEntries($article);
        if ($article === false) {
            return false;
        }

        $article['supplierID'] = $this->getSupplierId($article);
        if ($article['supplierID'] === false) {
            return false;
        }

        $article = $this->getTaxData($article);
        if ($article === false) {
            return false;
        }

        $article = $this->createOrUpdateArticle($article);
        $article = $this->createOrUpdateArticleDetail($article);

        $this->setArticleMainDetailId($article);
        $this->setArticlePrices($article);
        $this->setArticleAttributes($article);
        $article = $this->getConfiguratorData($article);
        // articleID and maindetailsID are required
        if ($article === false) {
            return false;
        }

        return [
            'articledetailsID' => $article['articledetailsID'],
            'articleID' => $article['articleID'],
            'articleattributesID' => $article['articleattributesID'],
            'kind' => $article['kind'],
            'supplierID' => $article['supplierID'],
            'tax' => $article['tax'],
            'taxID' => $article['taxID'],
        ];
    }

    /**
     * @param array $article
     *
     * @return bool|int|string
     */
    public function setConfiguratorData(array $article)
    {
        if (empty($article['articleID']) || empty($article['maindetailsID'])) {
            $this->logger->error("'articleID' and 'maindetailsID' are required!");

            return false;
        }

        $sql = '
            SELECT a.configurator_set_id, d.ordernumber
            FROM s_articles a
            LEFT JOIN s_articles_details d ON d.id = ?
            WHERE a.id = ?
        ';
        $result = $this->db->fetchRow($sql, [(int) $article['maindetailsID'], (int) $article['articleID']]);

        $orderNumber = $result['ordernumber'];
        $configuratorID = (int) $result['configurator_set_id'];
        if (empty($configuratorID)) {
            $name = $this->db->quote('Generated set ' . $result['ordernumber']);
            $sql = "SELECT id FROM s_article_configurator_sets WHERE name = $name";
            $configuratorID = $this->db->fetchOne($sql);
            if ($configuratorID === false) {
                $this->db->query("INSERT INTO s_article_configurator_sets (name, public, type) VALUES({$name}, 0, 0)");
                $configuratorID = (int) $this->db->lastInsertId();
            }
        }

        $groupNames = null;
        $additionalTextData = explode('|', $article['additionaltext']);
        if (isset($article['variant_group_names'])) {
            $variantGroupNames = explode('|', $article['variant_group_names']);
            // make sure that the number of group names matches the number of options
            $groupNames = count($additionalTextData) === count($variantGroupNames) ? $variantGroupNames : null;
        }

        $optionIDs = [];
        $groupIDs = [];
        foreach ($additionalTextData as $idx => $option) {
            $hidx = $idx + 1;
            $option = trim(str_replace("'", '', $option));

            if ($groupNames) {
                $genericGroupName = $this->db->quote($groupNames[$idx]);
            } else {
                $genericGroupName = $this->db->quote("Group #{$orderNumber}/{$hidx}");
            }
            $sql = "SELECT id
                    FROM s_article_configurator_groups
                    WHERE name = {$genericGroupName}";
            $result = $this->db->fetchOne($sql);

            if ($result === false) {
                $sql = "INSERT INTO s_article_configurator_groups (name, description, position)
                        VALUES({$genericGroupName}, '', 1)";
                $this->db->query($sql);
                $groupID = (int) $this->db->lastInsertId();
            } else {
                $groupID = (int) $result;
            }
            $optionName = $this->db->quote($option);
            $sql = "SELECT id
                    FROM s_article_configurator_options
                    WHERE name = {$optionName}
                      AND group_id = {$groupID}";
            $result = $this->db->fetchOne($sql);
            if ($result === false) {
                $sql = "INSERT INTO s_article_configurator_options (group_id,name,position)
                        VALUES ({$groupID}, {$optionName}, 1)";
                $this->db->query($sql);
                $optionIDs[] = (int) $this->db->lastInsertId();
            } else {
                $optionIDs[] = (int) $result;
            }

            $groupIDs[] = $groupID;
        }

        foreach ($groupIDs as $groupID) {
            // set-group relations
            $sql = "SELECT COUNT(*)
                    FROM s_article_configurator_set_group_relations
                    WHERE group_id = {$groupID}
                      AND set_id = {$configuratorID}";
            if ((int) $this->db->fetchOne($sql) === 0) {
                $sql = "INSERT INTO s_article_configurator_set_group_relations (set_id, group_id)
                        VALUES ({$configuratorID}, {$groupID})";
                $this->db->query($sql);
            }
        }

        foreach ($optionIDs as $optionID) {
            // set-option relations
            $sql = "SELECT COUNT(*)
                    FROM s_article_configurator_set_option_relations
                    WHERE option_id = {$optionID}
                      AND set_id = {$configuratorID}";
            if ((int) $this->db->fetchOne($sql) === 0) {
                $sql = "INSERT INTO s_article_configurator_set_option_relations (set_id, option_id)
                        VALUES ({$configuratorID}, {$optionID})";
                $this->db->query($sql);
            }

            // option-article relations
            $sql = "SELECT COUNT(*) FROM s_article_configurator_option_relations
                    WHERE option_id = {$optionID}
                      AND article_id = {$article['articledetailsID']}";
            if ((int) $this->db->fetchOne($sql) === 0) {
                $sql = "INSERT INTO s_article_configurator_option_relations (article_id, option_id)
                        VALUES ({$article['articledetailsID']}, {$optionID})";
                $this->db->query($sql);
            }
        }

        return $configuratorID;
    }

    /**
     * @param array $price
     *
     * @return bool|string
     */
    public function setPriceData(array $price)
    {
        if (isset($price['price'])) {
            $price['price'] = $this->toFloat($price['price']);
        }
        if (isset($price['tax'])) {
            $price['tax'] = $this->toFloat($price['tax']);
        }
        if (isset($price['pseudoprice'])) {
            $price['pseudoprice'] = $this->toFloat($price['pseudoprice']);
        } else {
            $price['pseudoprice'] = 0;
        }
        if (isset($price['baseprice'])) {
            $price['baseprice'] = $this->toFloat($price['baseprice']);
        } else {
            $price['baseprice'] = 0;
        }
        if (isset($price['percent'])) {
            $price['percent'] = $this->toFloat($price['percent']);
        } else {
            $price['percent'] = 0;
        }
        if (empty($price['from'])) {
            $price['from'] = 1;
        } else {
            $price['from'] = (int) $price['from'];
        }
        if (empty($price['pricegroup'])) {
            $price['pricegroup'] = 'EK';
        }
        $price['pricegroup'] = $this->db->quote($price['pricegroup']);

        if (!empty($price['tax'])) {
            $price['price'] = $price['price'] / (100 + $price['tax']) * 100;
        }
        if (isset($price['pseudoprice']) && !empty($price['tax'])) {
            $price['pseudoprice'] = $price['pseudoprice'] / (100 + $price['tax']) * 100;
        }

        $article = $this->getArticleNumbers($price);
        if (empty($article)) {
            return false;
        }

        if (empty($price['price']) && empty($price['percent'])) {
            return false;
        }

        if ($price['from'] <= 1 && empty($price['price'])) {
            return false;
        }

        // Delete old price, if pricegroup, articleDetailId and 'from' matches
        $sql = "DELETE FROM s_articles_prices
                WHERE pricegroup = {$price['pricegroup']}
                  AND articledetailsID = {$article['articledetailsID']}
                  AND CAST(`from` AS UNSIGNED) >= {$price['from']}";
        $this->db->query($sql);

        if (empty($price['price'])) {
            $sql = "SELECT price
                    FROM s_articles_prices
                    WHERE pricegroup = {$price['pricegroup']}
                        AND `from` = 1
                        AND articleID =  {$article['articleID']}
                        AND articledetailsID = {$article['articledetailsID']}";

            $price['price'] = $this->db->fetchOne($sql);
            if (empty($price['price'])) {
                return false;
            }

            $price['price'] = $price['price'] * (100 - $price['percent']) / 100;
        }

        if ($price['from'] != 1) {
            $sql = "UPDATE s_articles_prices
                    SET `to` = {$price['from']}-1
                    WHERE pricegroup = {$price['pricegroup']}
                      AND articleID =  {$article['articleID']}
                      AND articledetailsID = {$article['articledetailsID']}
                    ORDER BY `from` DESC
                    LIMIT 1";
            $result = $this->db->query($sql);
            if (empty($result) || !$result->rowCount()) {
                return false;
            }
        }

        $sql = "INSERT INTO s_articles_prices (
                  pricegroup,
                  `from`,
                  `to`,
                  articleID,
                  articledetailsID,
                  price,
                  pseudoprice,
                  baseprice,
                  percent
                )
                VALUES (
                  {$price['pricegroup']},
                  {$price['from']},
                  'beliebig',
                  {$article['articleID']},
                  {$article['articledetailsID']},
                  {$price['price']},
                  {$price['pseudoprice']},
                  {$price['baseprice']},
                  {$price['percent']}
                )";
        $result = $this->db->query($sql);

        if (empty($result)) {
            return false;
        }

        return $this->db->lastInsertId();
    }

    /**
     * @param array $article
     *
     * @return bool
     */
    public function deleteArticleLinks(array $article)
    {
        $articleID = $this->getArticleID($article);
        $sql = 'SELECT id
                FROM s_articles_information
                WHERE articleID =' . $articleID;
        $links = $this->db->fetchCol($sql);
        if (!empty($links)) {
            $this->deleteTranslation('link', $links);
        }

        $sql = 'DELETE FROM s_articles_information
                WHERE articleID =' . $articleID;
        $this->db->query($sql);

        return true;
    }

    /**
     * @param array $linkData
     *
     * @return bool|string
     */
    public function addArticleLink(array $linkData)
    {
        if (!($linkData['articleID'] = $this->getArticleID($linkData))) {
            return false;
        }
        if (empty($linkData) || !is_array($linkData) || empty($linkData['link']) || empty($linkData['description'])) {
            return false;
        }
        if (empty($linkData['target'])) {
            $linkData['target'] = '_blank';
        }
        $sql = 'INSERT INTO s_articles_information (articleID, description, link, target)
                VALUES (?, ?, ?, ?)';
        $this->db->query(
            $sql,
            [
                $linkData['articleID'],
                $linkData['description'],
                $linkData['link'],
                $linkData['target'],
            ]
        );

        return $this->db->lastInsertId();
    }

    /**
     * @param int $articleID
     *
     * @return bool
     */
    public function deleteImages($articleID)
    {
        /* @var ArticleRepository $articleRepository */
        $articleRepository = $this->getArticleRepository();
        $result = $articleRepository->getArticleImagesQuery($articleID)->getResult();
        /** @var \Shopware\Models\Article\Image $imageModel */
        foreach ($result as $imageModel) {
            $this->em->remove($imageModel);

            /* @var Media $media */
            $media = $imageModel->getMedia();
            if (!$media instanceof Media) {
                continue;
            }

            try {
                $this->em->remove($media);
            } catch (ORMException $e) {
                return false;
            }
        }

        $this->em->flush();

        return true;
    }

    /**
     * @return ArticleRepository
     */
    private function getArticleRepository()
    {
        if ($this->articleRepository === null) {
            $this->articleRepository = $this->em->getRepository(Article::class);
        }

        return $this->articleRepository;
    }

    /**
     * @return MediaRepository
     */
    private function getMediaRepository()
    {
        if ($this->mediaRepository === null) {
            $this->mediaRepository = $this->em->getRepository(Media::class);
        }

        return $this->mediaRepository;
    }

    /**
     * @param array $article
     *
     * @return array
     */
    private function prepareArticleData(array $article)
    {
        if (isset($article['name'])) {
            $article['name'] = $this->db->quote($this->toString($article['name']));
        }
        if (isset($article['shippingtime'])) {
            $article['shippingtime'] = $this->db->quote((string) $article['shippingtime']);
        }
        if (isset($article['description'])) {
            $article['description'] = $this->db->quote((string) $article['description']);
        }
        if (isset($article['description_long'])) {
            $article['description_long'] = $this->db->quote((string) $article['description_long']);
        }
        if (isset($article['keywords'])) {
            $article['keywords'] = $this->db->quote((string) $article['keywords']);
        }
        if (isset($article['supplierID'])) {
            $article['supplierID'] = (int) $article['supplierID'];
        }
        if (isset($article['taxID'])) {
            $article['taxID'] = (int) $article['taxID'];
        }
        if (isset($article['filtergroupID'])) {
            $article['filtergroupID'] = (int) $article['filtergroupID'];
        }
        if (isset($article['pricegroupID'])) {
            $article['pricegroupID'] = (int) $article['pricegroupID'];
        }
        if (isset($article['pseudosales'])) {
            $article['pseudosales'] = (int) $article['pseudosales'];
        }
        if (isset($article['topseller'])) {
            $article['topseller'] = empty($article['topseller']) ? 0 : 1;
        }
        if (isset($article['notification'])) {
            $article['notification'] = empty($article['notification']) ? 0 : 1;
        }

        if (isset($article['active'])) {
            $article['active'] = empty($article['active']) ? 0 : 1;
        }
        if (isset($article['crossbundlelook'])) {
            $article['crossbundlelook'] = empty($article['crossbundlelook']) ? 0 : 1;
        }
        if (isset($article['main_detail_id'])) {
            $article['main_detail_id'] = (int) $article['main_detail_id'];
        }
        if (isset($article['configurator_set_id'])) {
            $article['configurator_set_id'] = (int) $article['configurator_set_id'];
        }
        if (isset($article['template'])) {
            $article['template'] = (int) $article['template'];
        }
        if (isset($article['mode'])) {
            $article['mode'] = (int) $article['mode'];
        }
        if (!empty($article['added'])) {
            $article['added'] = $this->toDate($article['added']);
        } else {
            unset($article['added']);
        }
        if (!empty($article['changed'])) {
            $article['changed'] = $this->toTimeStamp($article['changed']);
        } else {
            unset($article['changed']);
        }
        if (!empty($article['available_from'])) {
            $article['available_from'] = $this->toTimeStamp($article['available_from']);
        } else {
            unset($article['available_from']);
        }
        if (!empty($article['available_to'])) {
            $article['available_to'] = $this->toTimeStamp($article['available_to']);
        } else {
            unset($article['available_to']);
        }

        return $article;
    }

    /**
     * @param array $article
     *
     * @return array
     */
    private function prepareArticleDetailData(array $article)
    {
        if (isset($article['articleID'])) {
            $article['articleID'] = (int) $article['articleID'];
        }
        if (isset($article['ordernumber'])) {
            $article['ordernumber'] = $this->db->quote($this->toString($article['ordernumber']));
        }
        if (isset($article['impressions'])) {
            $article['impressions'] = (int) $article['impressions'];
        }
        if (isset($article['sales'])) {
            $article['sales'] = (int) $article['sales'];
        }
        if (isset($article['position'])) {
            $article['position'] = (int) $article['position'];
        }
        if (isset($article['width'])) {
            $article['width'] = $this->toFloat($article['width']);
        }
        if (isset($article['height'])) {
            $article['height'] = $this->toFloat($article['height']);
        }
        if (isset($article['length'])) {
            $article['length'] = $this->toFloat($article['length']);
        }
        if (isset($article['ean'])) {
            $article['ean'] = $this->db->quote((string) $article['ean']);
        }
        if (isset($article['unitID'])) {
            $article['unitID'] = (int) $article['unitID'];
        }
        if (isset($article['purchasesteps'])) {
            $article['purchasesteps'] = (int) $article['purchasesteps'];
        }
        if (isset($article['maxpurchase'])) {
            $article['maxpurchase'] = (int) $article['maxpurchase'];
        }
        if (isset($article['minpurchase'])) {
            $article['minpurchase'] = (int) $article['minpurchase'];
        }
        if (isset($article['purchaseunit'])) {
            $article['purchaseunit'] = $this->toFloat($article['purchaseunit']);
        }
        if (isset($article['referenceunit'])) {
            $article['referenceunit'] = $this->toFloat($article['referenceunit']);
        }
        if (isset($article['packunit'])) {
            $article['packunit'] = $this->db->quote((string) $article['packunit']);
        }
        if (isset($article['shippingfree'])) {
            $article['shippingfree'] = empty($article['shippingfree']) ? 0 : 1;
        }
        if (!empty($article['releasedate'])) {
            $article['releasedate'] = $this->toDate($article['releasedate']);
        } elseif (isset($article['releasedate'])) {
            $article['releasedate'] = null;
        }
        if (isset($article['laststock'])) {
            $article['laststock'] = empty($article['laststock']) ? 0 : 1;
        }

        $article['stockmin'] = empty($article['stockmin']) ? 0 : (int) $article['stockmin'];
        $article['instock'] = empty($article['instock']) ? 0 : (int) $article['instock'];
        $article['weight'] = empty($article['weight']) ? 0 : $this->toFloat($article['weight']);
        $article['additionaltext'] = empty($article['additionaltext']) ? "''" : $this->db->quote((string) $article['additionaltext']);
        $article['suppliernumber'] = empty($article['suppliernumber']) ? "''" : $this->db->quote($this->toString($article['suppliernumber']));

        return $article;
    }

    /**
     * @param array $article
     *
     * @return array
     */
    private function prepareArticleAttributesData(array $article)
    {
        if (isset($article['articledetailsID'])) {
            $article['articledetailsID'] = (int) $article['articledetailsID'];
        }

        if (isset($article['attr']) && is_array($article['attr'])) {
            foreach ($article['attr'] as $attrKey => $attrValue) {
                $key = (int) str_replace('attr', '', $attrKey);
                if (is_int($key) && $key <= 20) {
                    $article['attr'][$attrKey] = $this->db->quote((string) $attrValue);
                } else {
                    unset($article['attr'][$attrKey]);
                }
            }
        } else {
            $article['attr'] = [];
        }

        for ($i = 1; $i <= 20; ++$i) {
            if (isset($article["attr$i"])) {
                $article['attr']["attr$i"] = $this->db->quote((string) $article["attr$i"]);
                unset($article["attr$i"]);
            }
        }

        return $article;
    }

    /**
     * @param array $article
     *
     * @return bool|array
     */
    private function findExistingEntries(array $article)
    {
        // checks whether main detail exists
        if (!empty($article['maindetailsID'])) {
            $article['maindetailsID'] = (int) $article['maindetailsID'];
            $sql = 'SELECT id, articleID
                    FROM s_articles_details
                    WHERE id = ? AND kind = 1';
            $mainDetailIds = $this->db->fetchRow($sql, [$article['maindetailsID']]);
            if (empty($mainDetailIds['id'])) {
                $this->logger->error("Main article with id = '{$article['maindetailsID']}' not found!");

                return false;
            }

            $article['maindetailsID'] = (int) $mainDetailIds['id'];
            $article['articleID'] = (int) $mainDetailIds['articleID'];
        }

        $where = '1';
        // checks whether current detail exists
        if (!empty($article['articledetailsID'])) {
            $where = "d.id = {$article['articledetailsID']}";
        } elseif (!empty($article['ordernumber'])) {
            $where = "d.ordernumber = {$article['ordernumber']}";
        } elseif (!empty($article['articleID'])) {
            $where = "d.articleID = {$article['articleID']} AND d.kind = 1";
        }
        $sql = "SELECT d.id, d.articleID, d.kind, a.taxID
                FROM s_articles a, s_articles_details d
                WHERE a.id=d.articleID
                AND {$where}";
        $detailData = $this->db->fetchRow($sql);

        // set detail's kind
        if (empty($article['maindetailsID'])
            || (!empty($detailData['id']) && $article['maindetailsID'] == $detailData['id'])
        ) {
            $article['kind'] = 1;
        } else {
            $article['kind'] = 2;
        }

        if (empty($detailData['id']) && !isset($article['ordernumber'])) {
            $this->logger->error('Article for update not found!');

            return false;
        }

        if (!empty($detailData) && $detailData['kind'] == 1 && $article['kind'] == 2) {
            $this->deleteArticle($detailData['articleID']);
            unset($detailData);
        } elseif (!empty($detailData)) {
            $article['articledetailsID'] = $detailData['id'];
            if ($article['kind'] == 1) {
                $article['articleID'] = $detailData['articleID'];
            }
            if (empty($article['taxID']) && empty($article['tax'])) {
                $article['taxID'] = $detailData['taxID'];
            }
        }

        return $article;
    }

    /**
     * @param array $article
     *
     * @return bool|int
     */
    private function getSupplierId(array $article)
    {
        if (isset($article['supplierID'])) {
            $article['supplierID'] = $this->getSupplierIdById($article['supplierID']);
        } elseif (isset($article['supplier'])) {
            $article['supplierID'] = $this->getSupplierIdByName($article['supplier']);
        }

        if (empty($article['supplierID']) && empty($article['articleID'])) {
            $this->logger->error('Supplier is required!');

            return false;
        }

        return $article['supplierID'];
    }

    /**
     * @param string $supplierId
     *
     * @return bool|int
     */
    private function getSupplierIdById($supplierId)
    {
        if (empty($supplierId)) {
            return false;
        }

        $supplierId = (int) $supplierId;

        $sql = 'SELECT id FROM s_articles_supplier WHERE id = ' . $supplierId;
        $id = $this->db->fetchOne($sql);
        if (empty($id)) {
            return false;
        }

        return $supplierId;
    }

    /**
     * @param string $supplierName
     *
     * @return int
     */
    private function getSupplierIdByName($supplierName)
    {
        $supplier['name'] = $this->db->quote($this->toString($supplierName));

        $sql = "SELECT id, img, link FROM s_articles_supplier WHERE name = {$supplier['name']}";
        $supplierData = $this->db->fetchRow($sql);

        $supplierID = $supplierData['id'];
        $supplier['img'] = $this->db->quote($supplierData['img']);
        $supplier['link'] = $this->db->quote($supplierData['link']);

        if (empty($supplierID)) {
            $sql = "
                INSERT INTO s_articles_supplier (name, img, link)
                VALUES ({$supplier['name']}, {$supplier['img']}, {$supplier['link']})
            ";
            $this->db->query($sql);
            $supplierID = $this->db->lastInsertId();
        } else {
            $sql = "UPDATE s_articles_supplier
                    SET name = {$supplier['name']}, img = {$supplier['img']}, link = {$supplier['link']}
                    WHERE id = {$supplierID}";
            $this->db->query($sql);
        }

        return (int) $supplierID;
    }

    /**
     * @param array $article
     *
     * @return bool|array
     */
    private function getTaxData(array $article)
    {
        if (!empty($article['taxID'])) {
            $where = 'WHERE id = ' . $article['taxID'];
        } elseif (isset($article['tax'])) {
            $where = 'WHERE tax = ' . $this->toFloat($article['tax']);
        } else {
            $where = 'ORDER BY id';
        }

        $sql = "SELECT id AS taxID, tax
                FROM s_core_tax {$where}
                LIMIT 1";
        $row = $this->db->fetchRow($sql);
        if (empty($row)) {
            $this->logger->error('Tax rate not found!');

            return false;
        }

        $article['taxID'] = (int) $row['taxID'];
        $article['tax'] = $row['tax'];

        return $article;
    }

    /**
     * @param array $article
     *
     * @return array
     */
    private function createOrUpdateArticle(array $article)
    {
        if (empty($article['articleID'])) {
            $article['taxID'] = empty($article['taxID']) ? 1 : $article['taxID'];
            $article['datum'] = empty($article['added']) ? 'CURDATE()' : $article['added'];
            $article['changetime'] = empty($article['changed']) ? 'NOW()' : $article['changed'];
            $article['active'] = (!isset($article['active']) || $article['active'] == 1) ? 1 : 0;

            $insertFields = [];
            $insertValues = [];
            foreach ($this->articleFields as $field) {
                if (isset($article[$field])) {
                    $insertFields[] = $field;
                    $insertValues[] = $article[$field];
                }
            }
            $values = '(' . implode(', ', $insertFields) . ') VALUES (' . implode(', ', $insertValues) . ')';
            $sql = "INSERT INTO s_articles $values";
            $this->db->query($sql);
            $article['articleID'] = $this->db->lastInsertId();
        } else {
            $sql = 'UPDATE s_articles
                    SET changetime = NOW()
                    WHERE id = ?';
            $this->db->query($sql, [$article['articleID']]);
        }

        return $article;
    }

    /**
     * @param array $article
     *
     * @return array
     */
    private function createOrUpdateArticleDetail(array $article)
    {
        if (empty($article['articledetailsID'])) {
            $article['active'] = (!isset($article['active']) || $article['active'] == 1) ? 1 : 0;
            $article['datum'] = empty($article['added']) ? 'CURDATE()' : $article['added'];
            $article['changetime'] = empty($article['changed']) ? 'NOW()' : $article['changed'];

            $insertFields = [];
            $insertValues = [];
            foreach ($this->articleDetailFields as $field) {
                if (isset($article[$field])) {
                    $insertFields[] = $field;
                    $insertValues[] = $article[$field];
                }
            }
            $values = '(' . implode(', ', $insertFields) . ') VALUES (' . implode(', ', $insertValues) . ')';
            $sql = "INSERT INTO s_articles_details $values";
            $this->db->query($sql);
            $article['articledetailsID'] = $this->db->lastInsertId();
        } else {
            $values = [];
            foreach ($this->articleDetailFields as $field) {
                if (isset($article[$field])) {
                    $values[] = $field . '=' . $article[$field];
                }
            }
            $values = implode(', ', $values);
            $sql = "UPDATE s_articles_details
                    SET $values
                    WHERE id = {$article['articledetailsID']}";
            $this->db->query($sql);
        }

        return $article;
    }

    /**
     * @param array $article
     */
    private function setArticleMainDetailId(array $article)
    {
        if ($article['kind'] !== 1) {
            return;
        }

        $sql = 'UPDATE s_articles
                SET main_detail_id = ?
                WHERE id = ?';
        $this->db->query($sql, [$article['articledetailsID'], $article['articleID']]);
    }

    /**
     * @param array $article
     */
    private function setArticlePrices(array $article)
    {
        $this->db->update(
            's_articles_prices',
            ['articleID' => $article['articleID']],
            ['articledetailsID = ?' => $article['articledetailsID']]
        );
    }

    /**
     * @param array $article
     *
     * @return array
     */
    private function setArticleAttributes(array $article)
    {
        $sql = 'SELECT id
                FROM s_articles_attributes
                WHERE articledetailsID = ?';
        $article['articleattributesID'] = $this->db->fetchOne($sql, [$article['articledetailsID']]);

        $values = '';
        $columns = '';
        if (!empty($article['articleattributesID'])) {
            foreach ($article['attr'] as $key => $value) {
                $values .= ", $key = $value";
            }

            if ($values === '') {
                return $article;
            }

            $sql = "UPDATE s_articles_attributes
                    SET $values
                    WHERE articledetailsID = {$article['articledetailsID']}";
            $this->db->query($sql);
        } else {
            if (!empty($article['attr'])) {
                $columns = ', ' . implode(', ', array_keys($article['attr']));
                $values = ', ' . implode(', ', $article['attr']);
            }

            if ($values === '') {
                return $article;
            }

            $sql = "INSERT INTO s_articles_attributes
                    (articledetailsID $columns) VALUES
                    ({$article['articleID']}, {$article['articledetailsID']} $values)";

            $this->db->query($sql);
            $article['articleattributesID'] = $this->db->lastInsertId();
        }

        return $article;
    }

    /**
     * @param array $article
     *
     * @return bool|array
     */
    private function getConfiguratorData(array $article)
    {
        $configuratorSetId = null;
        if (!empty($article['maindetailsID'])) {
            $configuratorSetId = $this->setConfiguratorData($article);
            if ($configuratorSetId === false) {
                return false;
            }
        }

        // Set configurator set id
        if ($configuratorSetId !== null && $configuratorSetId !== (int) $article['configurator_set_id']) {
            $sql = "UPDATE s_articles
                    SET configurator_set_id = {$configuratorSetId}
                    WHERE id = {$article['articleID']}";
            $this->db->query($sql);
        }

        return $article;
    }

    /**
     * @param array $article
     *
     * @return bool|array
     */
    private function getArticleNumbers(array $article)
    {
        if (empty($article['articleID'])
            && empty($article['articledetailsID'])
            && empty($article['ordernumber'])
        ) {
            return false;
        }
        if (!empty($article['articledetailsID'])) {
            $article['articledetailsID'] = (int) $article['articledetailsID'];
            $where = "id = {$article['articledetailsID']}";
        } elseif (!empty($article['articleID'])) {
            $article['articleID'] = (int) $article['articleID'];
            $where = "articleID = {$article['articleID']} AND kind = 1";
        } else {
            $article['ordernumber'] = $this->db->quote((string) $article['ordernumber']);
            $where = "ordernumber = {$article['ordernumber']}";
        }
        $sql = "SELECT id AS articledetailsID, ordernumber, articleID
                FROM s_articles_details
                WHERE {$where}";
        $numbers = $this->db->fetchRow($sql);
        if (empty($numbers['articledetailsID'])) {
            return false;
        }

        return $numbers;
    }

    /**
     * @param array $article
     *
     * @return bool|string
     */
    private function getArticleID(array $article)
    {
        if (empty($article)) {
            return false;
        }
        if (is_string($article)) {
            $article = ['ordernumber' => $article];
        }
        if (is_int($article)) {
            $article = ['articleID' => $article];
        }
        if (!is_array($article)) {
            return false;
        }
        if (empty($article['articleID']) && empty($article['articledetailsID']) && empty($article['ordernumber'])) {
            return false;
        }

        $sql = 'SELECT articleID
                FROM s_articles_details
                WHERE ';
        if (!empty($article['articleID'])) {
            $article['articleID'] = (int) $article['articleID'];
            $sql .= "articleID = {$article['articleID']}";
        } elseif (!empty($article['articledetailsID'])) {
            $article['articledetailsID'] = (int) $article['articledetailsID'];
            $sql .= "id = {$article['articledetailsID']}";
        } else {
            $article['ordernumber'] = $this->db->quote((string) $article['ordernumber']);
            $sql .= "ordernumber = {$article['ordernumber']}";
        }
        $articleId = $this->db->fetchOne($sql);
        if (empty($articleId)) {
            return false;
        }

        return $articleId;
    }

    /**
     * @param string|array      $type
     * @param null|string|array $objectKey
     *
     * @return bool
     */
    private function deleteTranslation($type, $objectKey = null)
    {
        if (empty($type)) {
            return false;
        }

        if (is_array($type)) {
            foreach ($type as &$value) {
                $value = $this->db->quote($value);
            }
            unset($value);
            $type = implode(',', $type);
        } else {
            $type = $this->db->quote($type);
        }

        $sql = 'DELETE FROM s_core_translations
                WHERE objecttype IN (' . $type . ')';
        if (!empty($objectKey)) {
            if (is_array($objectKey)) {
                foreach ($objectKey as &$value) {
                    $value = $this->db->quote($value);
                }
                $objectKey = implode(',', $objectKey);
            } else {
                $objectKey = $this->db->quote($objectKey);
            }
            $sql .= ' AND objectkey IN (' . $objectKey . ')';
        }

        $result = $this->db->query($sql, [$type]);

        return (bool) $result;
    }

    /**
     * Delete article method
     *
     * @param int $articleID
     *
     * @return bool
     */
    private function deleteArticle($articleID)
    {
        $article['articleID'] = (int) $articleID;
        $article['kind'] = 1;
        $sql = "SELECT id
                FROM s_articles_details
                WHERE articleID = {$article['articleID']}";
        $article['articledetailsID'] = $this->db->fetchOne($sql);
        $sql = "SELECT ordernumber
                FROM s_articles_details
                WHERE articleID = {$article['articleID']}";
        $article['ordernumber'] = $this->db->fetchOne($sql);
        if (empty($article['articledetailsID'])) {
            return false;
        }

        $this->deleteImages($article['articleID']);
        $this->deleteDownloads($article['articleID']);
        $this->deleteArticleLinks($article);
        $this->deletePermissions($article['articleID']);

        // delete products
        $sql = 'DELETE FROM s_articles
                WHERE id = ' . $article['articleID'];
        $this->db->query($sql);

        // delete esd products
        $sql = 'SELECT id
                FROM s_articles_esd
                WHERE articleID = ' . $article['articleID'];
        $esdIDs = $this->db->fetchCol($sql);
        if (!empty($esdIDs)) {
            $sql = 'DELETE FROM s_articles_esd_serials
                    WHERE esdID = ' . implode(' OR esdID=', $esdIDs);
            $this->db->query($sql);
        }

        $sql = 'DELETE FROM s_articles_attributes WHERE articledetailsID = ' . $article['articledetailsID'];
        $this->db->query($sql);

        $tables = [
            's_articles_details',
            's_articles_esd',
            's_articles_prices',
            's_articles_relationships',
            's_articles_similar',
            's_articles_vote',
            's_articles_categories',
            's_articles_translations',
            's_export_articles',
            's_emarketing_lastarticles',
            's_articles_avoid_customergroups',
        ];
        foreach ($tables as $table) {
            $sql = "DELETE FROM $table
                    WHERE articleID = {$article['articleID']}";
            $this->db->query($sql);
        }
        $this->deleteTranslation(
            [
                'article',
                'configuratoroption',
                'configuratorgroup',
                'accessoryoption',
                'accessorygroup',
                'propertyvalue',
            ],
            $article['articleID']
        );

        $sql = 'DELETE FROM s_core_rewrite_urls
                WHERE org_path = ?';
        $this->db->query($sql, ['sViewport=detail&sArticle=' . $article['articleID']]);

        $tables = [
            's_articles_similar' => 'relatedarticle',
            's_articles_relationships' => 'relatedarticle',
        ];
        foreach ($tables as $table => $row) {
            $sql = "DELETE FROM $table WHERE $row = ?";
            $this->db->query($sql, [$article['ordernumber']]);
        }

        $this->deleteTranslation('objectkey', $article['articledetailsID']);

        return true;
    }

    /**
     * @param int $articleID
     *
     * @return bool
     */
    private function deleteDownloads($articleID)
    {
        /* @var ArticleRepository $articleRepository */
        $articleRepository = $this->getArticleRepository();
        $article = $articleRepository->find($articleID);
        if (!$article instanceof Article) {
            return false;
        }

        $downloads = $article->getDownloads();
        foreach ($downloads as $downloadModel) {
            $filename = $downloadModel->getFile();
            $this->em->remove($downloadModel);

            /* @var MediaRepository $mediaRepository */
            $mediaRepository = $this->getMediaRepository();
            $mediaList = $mediaRepository->findBy(
                ['path' => $filename]
            );
            foreach ($mediaList as $mediaModel) {
                $this->em->remove($mediaModel);
            }
        }

        $sql = 'SELECT id FROM s_articles_downloads WHERE articleID = ' . $articleID;
        $downloads = $this->db->fetchCol($sql);
        if (!empty($downloads)) {
            $this->deleteTranslation('download', $downloads);
        }

        $this->em->flush();

        return true;
    }

    /**
     * @param int $articleID
     *
     * @return bool
     */
    private function deletePermissions($articleID)
    {
        $sql = ' DELETE FROM s_articles_avoid_customergroups WHERE articleID = ?';
        $this->db->query($sql, [$articleID]);

        return true;
    }

    /**
     * Clear description method
     *
     * @param string $description
     *
     * @return string
     */
    private function toString($description)
    {
        $description = html_entity_decode($description);
        $description = preg_replace('!<[^>]*?>!', ' ', $description);
        $description = str_replace(chr(0xa0), ' ', $description);
        $description = preg_replace('/\s\s+/', ' ', $description);
        $description = htmlspecialchars($description);
        $description = trim($description);

        return $description;
    }

    /**
     * Replace comma with point for decimal delimiter
     *
     * @param float $value
     *
     * @return float
     */
    private function toFloat($value)
    {
        if (is_float($value)) {
            return $value;
        }

        return (float) str_replace(',', '.', $value);
    }

    /**
     * Returns database timestamp
     *
     * @param string $timestamp
     *
     * @return string
     */
    private function toDate($timestamp)
    {
        if (empty($timestamp) && $timestamp !== 0) {
            return 'null';
        }
        $date = new \Zend_Date($timestamp);

        return $this->db->quote($date->toString('Y-m-d', 'php'));
    }

    /**
     * Returns database timestamp
     *
     * @param string $timestamp
     *
     * @return string
     */
    private function toTimeStamp($timestamp)
    {
        if (empty($timestamp) && $timestamp !== 0) {
            return 'null';
        }
        $date = new \Zend_Date($timestamp);

        return $this->db->quote($date->toString('Y-m-d H:i:s', 'php'));
    }
}
