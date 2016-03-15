<?php
/**
 * (c) shopware AG <info@shopware.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Shopware\SwagMigration\Components\Migration;

use Shopware\SwagMigration\Components\Migration;
use Exception;
use Shopware\SwagMigration\Components\DbServices\DeleteService;

/**
 * Helper to clean up the target shop
 *
 * @category  Shopware
 * @package Shopware\Plugins\SwagMigration\Components\Migration
 * @copyright Copyright (c) 2012, shopware AG (http://www.shopware.de)
 */
class Cleanup
{
    /**
     * Constructor: Disable foreign key checks
     */
    public function __construct()
    {
        // Disable foreign key checks
        Shopware()->Db()->exec("SET foreign_key_checks = 0;");
    }

    /**
     * Performs cleanup by a list of given operations
     *
     * @param $data
     */
    public function cleanUpByArray($data)
    {
        /* @var DeleteService $deleteService */
        $deleteService = Shopware()->Container()->get('swagmigration.deleteService');

        foreach ($data as $key => $value) {
            switch ($key) {
                case 'clear_customers':
                    $this->sDeleteAllCustomers();
                    $this->removeMigrationMappingsByType(Migration::MAPPING_CUSTOMER);
                    break;
                case 'clear_orders':
                    $this->sDeleteAllCustomers();
                    $this->sDeleteAllOrders();
                    $this->removeMigrationMappingsByType(Migration::MAPPING_CUSTOMER);
                    $this->removeMigrationMappingsByType(Migration::MAPPING_ORDER);
                    break;
                case 'clear_votes':
                    Shopware()->Db()->exec("TRUNCATE s_articles_vote;");
                    break;
                case 'clear_articles':
                    $this->sDeleteAllArticles();
                    $this->removeMigrationMappingsByType(Migration::MAPPING_ARTICLE);
                    try {
                        Shopware()->Db()->query('TRUNCATE s_articles_categories_seo;');
                    } catch (Exception $e) {
                        // if table does not exist - resume, it might be just an old SW version
                    }
                    break;
                case 'clear_categories':
                    $deleteService->deleteAllCategories();
                    $this->removeMigrationMappingsByType(Migration::MAPPING_CATEGORY);
                    $this->removeMigrationMappingsByType(Migration::MAPPING_CATEGORY_TARGET);
                    try {
                        Shopware()->Db()->query('TRUNCATE s_articles_categories_ro;');
                    } catch (Exception $e) {
                        // if table does not exist - resume, it might be just an old SW version
                    }
                    break;
                case 'clear_supplier':
                    // As one might want to clear the suppliers without leaving all related articles
                    // invalid, we create a new 'Default'-Supplier and set it for all articles
                    Shopware()->Db()->exec(
                        "
                        TRUNCATE s_articles_supplier;
                        TRUNCATE s_articles_supplier_attributes;
                        INSERT INTO s_articles_supplier (`id`, `name`) VALUES (1, 'Default');
                        INSERT INTO s_articles_supplier_attributes (`id`) VALUES (1);
                        UPDATE s_articles SET supplierID=1 WHERE 1;
                    "
                    );
                    break;
                case 'clear_properties':
                    $this->sDeleteAllFilters();
                    break;
                case 'clear_mappings':
                    $this->clearMigrationMappings();
                    break;
                case 'clear_images':
                    $this->clearImages();
                    break;
                case 'clear_article_downloads':
                    $this->sDeleteArticleDownloads();
                    break;
                case 'clear_esd_article_downloads':
                    $this->sDeleteEsdArticleDownloads();
                    break;

                default:
                    break;
            }
        }
    }

    /**
     * Truncates the migration mapping table
     */
    public function clearMigrationMappings()
    {
        $sql = '
            TRUNCATE `s_plugin_migrations`;
        ';
        Shopware()->Db()->query($sql);
    }

    /**
     * Remove mappings by a given type
     *
     * @param $type
     */
    public function removeMigrationMappingsByType($type)
    {
        $sql = 'DELETE FROM s_plugin_migrations WHERE typeID = ?';
        Shopware()->Db()->query($sql, [$type]);
    }

    /**
     * Truncate all article related tables
     */
    public function sDeleteAllArticles()
    {
        $sql = "
            SET foreign_key_checks = 0;
			TRUNCATE s_articles;
			TRUNCATE s_filter_articles;
			TRUNCATE s_articles_attributes;
			TRUNCATE s_articles_avoid_customergroups;
			TRUNCATE s_articles_categories;
			TRUNCATE s_articles_details;
			TRUNCATE s_articles_downloads;
			TRUNCATE s_articles_downloads_attributes;
			TRUNCATE s_articles_esd;
			TRUNCATE s_articles_esd_attributes;
			TRUNCATE s_articles_esd_serials;
			TRUNCATE s_articles_img;
			TRUNCATE s_articles_img_attributes;
			TRUNCATE s_articles_information;
			TRUNCATE s_articles_information_attributes;
			TRUNCATE s_articles_notification;
            TRUNCATE s_articles_prices_attributes;
			TRUNCATE s_articles_prices;
			TRUNCATE s_articles_relationships;
			TRUNCATE s_articles_similar;
			TRUNCATE s_articles_translations;
			TRUNCATE s_article_configurator_dependencies;
			TRUNCATE s_article_configurator_groups;
			TRUNCATE s_article_configurator_options;
			TRUNCATE s_article_configurator_option_relations;
			TRUNCATE s_article_configurator_price_variations;
			TRUNCATE s_article_configurator_set_group_relations;
			TRUNCATE s_article_configurator_set_option_relations;
			TRUNCATE s_article_configurator_sets;
            TRUNCATE s_article_configurator_templates_attributes;
            TRUNCATE s_article_configurator_template_prices_attributes;
            TRUNCATE s_article_configurator_template_prices;
			TRUNCATE s_article_configurator_templates;
			TRUNCATE s_article_img_mapping_rules;
			TRUNCATE s_article_img_mappings;
            SET foreign_key_checks = 1;
        ";

        Shopware()->Db()->query($sql);

        try {
            // Follow-up: Truncate the tables that were not cleared in the first round
            Shopware()->Db()->query('TRUNCATE s_article_configurator_accessory_groups;');
            Shopware()->Db()->query('TRUNCATE s_article_configurator_accessory_articles;');
            Shopware()->Db()->query('TRUNCATE s_article_configurator_sets;');
            Shopware()->Db()->query('TRUNCATE s_article_configurator_set_group_relations;');
            Shopware()->Db()->query('TRUNCATE s_article_configurator_set_option_relations;');
            Shopware()->Db()->query('TRUNCATE s_article_configurator_templates;');
            Shopware()->Db()->query('TRUNCATE s_article_configurator_templates_attributes;');
            Shopware()->Db()->query('TRUNCATE s_article_configurator_template_prices;');
            Shopware()->Db()->query('TRUNCATE s_article_configurator_price_variations;');

            Shopware()->Db()->query('TRUNCATE s_articles_categories_ro;');
        } catch (Exception $e) {
            // if table does not exist - resume, it might be just an old SW version
        }
    }

    /**
     * Truncate order related tables.
     */
    public function sDeleteAllOrders()
    {
        $sql = "
            SET foreign_key_checks = 0;
            TRUNCATE s_order;
            TRUNCATE s_order_attributes;
            TRUNCATE s_order_basket;
            TRUNCATE s_order_basket_attributes;
            TRUNCATE s_order_billingaddress;
            TRUNCATE s_order_billingaddress_attributes;
            TRUNCATE s_order_comparisons;
            TRUNCATE s_order_details;
            TRUNCATE s_order_details_attributes;
            TRUNCATE s_order_shippingaddress;
            TRUNCATE s_order_shippingaddress_attributes;
            TRUNCATE s_order_documents;
            TRUNCATE s_order_documents_attributes;
            TRUNCATE s_order_esd;
            TRUNCATE s_order_history;
            TRUNCATE s_order_notes;
            SET foreign_key_checks = 1;
        ";

        Shopware()->Db()->query($sql);
    }

    /**
     * Truncate customer related tables
     */
    public function sDeleteAllCustomers()
    {
        $sql = "
            SET foreign_key_checks = 0;
            TRUNCATE s_user;
            TRUNCATE s_user_attributes;
            TRUNCATE s_user_billingaddress;
            TRUNCATE s_user_billingaddress_attributes;
            TRUNCATE s_user_shippingaddress;
            TRUNCATE s_user_shippingaddress_attributes;
            TRUNCATE s_user_shippingaddress_attributes;
            TRUNCATE s_user_debit;
            SET foreign_key_checks = 1;
	   ";

        Shopware()->Db()->query($sql);
    }

    /**
     * Helper method to delete all filter properties
     */
    public function sDeleteAllFilters()
    {
        $sql = '
            SET foreign_key_checks = 0;
			TRUNCATE s_filter;
			TRUNCATE s_filter_articles;
			TRUNCATE s_filter_attributes;
			TRUNCATE s_filter_options;
			TRUNCATE s_filter_relations;
			TRUNCATE s_filter_values;
            SET foreign_key_checks = 1;
		';

        Shopware()->Db()->query($sql);
    }

    /**
     * Helper method which deletes images/media tables
     * Also physically deletes corresponding files
     */
    public function clearImages()
    {
        $sql = '
            SET foreign_key_checks = 0;
			TRUNCATE s_articles_img;
			TRUNCATE s_articles_img_attributes;
			TRUNCATE s_article_img_mappings;
			TRUNCATE s_article_img_mapping_rules;
			TRUNCATE s_media;
            SET foreign_key_checks = 1;
		';
        Shopware()->Db()->query($sql);

        $this->clearFolder(Shopware()->DocPath('media/image'), 'image');
        $this->clearFolder(Shopware()->DocPath('media/image/thumbnail'), 'image');
    }

    private function sDeleteArticleDownloads()
    {
        // Truncate tables
        Shopware()->Db()->query("TRUNCATE s_articles_downloads");

        // delete files
        $this->clearFolder(Shopware()->DocPath('files/downloads'));
    }

    private function sDeleteEsdArticleDownloads()
    {
        // Truncate tables
        Shopware()->Db()->query("TRUNCATE s_articles_esd");

        // delete files
        $this->clearFolder(
            Shopware()->DocPath('files') . Shopware()->Config()->get('sESDKEY')
        );
    }

    /**
     * Cleans a folder
     *
     * @param $path string folder to clean
     * @param $type string file type of files - one of 'image' | null
     */
    private function clearFolder($path, $type = null)
    {
        if ($handle = opendir($path)) {
            while (false !== ($file = readdir($handle))) {
                switch ($type) {
                    case 'image':
                        // only delete .jpg, .jpeg, .png and .gif; ignore case
                        if (preg_match('/.jpg|.jpeg|.png|.gif/i', $file)) {
                            unlink($path . $file);
                        }
                        break;
                    default:
                        unlink($path . $file);
                }
            }
        }
    }
}
