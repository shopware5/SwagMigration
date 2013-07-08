﻿<?php
/**
 * Shopware 4.0
 * Copyright © 2012 shopware AG
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

/**
 * Shopware SwagMigration Plugin - Migration Backend Controller
 *
 * @category  Shopware
 * @package   Shopware\Plugins\SwagMigration\Controllers\Backend
 * @copyright Copyright (c) 2012, shopware AG (http://www.shopware.de)
 */
class Shopware_Controllers_Backend_SwagMigration extends Shopware_Controllers_Backend_ExtJs
{
	/**
	 * Some helpers
	 * @var Shopware_Components_Migration_Helpers
	 */
	protected $helpers;

    /**
     * Defines all availabe imports as well as the order of their import
     * @var array
     */
    public $imports = array(
        'import_products' => 'Product',
        'import_translations' => 'Translation',
        'import_properties' => 'Property',
        'import_categories' => 'Category',
        'import_article_categories' => 'Category',
        'import_prices' => 'Price',
        'import_generate_variants' => 'Configurator',
        'import_create_configurator_variants' => 'Variant',
        'import_images' => 'Image',
        'import_customers' => 'Customer',
        'import_ratings' => 'Rating',
        'import_orders' => 'Order',
        'import_order_details' => 'Order'
    );

    /**
     * Source shop system profile
     * @var Shopware_Components_Migration_Profile
     */
    protected $source;

    /**
     * Target shop system profile
     * @var Shopware_Components_Migration_Profile
     */
    protected $target;

    /**
     * Namespace for the snippets
     */
    protected $namespace;

    /**
     * Default execution time. After the given number of seconds, the current offset is saved and the
     * request is returned to the ExtJS controller which triggered it
     *
     * @var int
     */
    protected $max_execution = 10;

	/**
     * This function add the template directory and register the Shopware_Components namespace
     */
    public function init()
    {
        Shopware()->Loader()->registerNamespace('Shopware_Components', dirname(__FILE__).'/../../Components/');
        $this->View()->addTemplateDir(dirname(__FILE__) . "/../../Views/");
        parent::init();
    }

    /**
     * This function inits the source profile and creates it over the profile factory
     * @return Enlight_Class
     */
    public function initSource()
    {
        $config = (array) Shopware()->getOption('db');

        // Setting the current shopware database as default will fail,
        // if the user wants to connect to a remote database. So the dbname
        // needs to be unset
        $config['dbname'] = "";

        // Populate the config object by the request data
        $query = $this->Request()->getPost()+$this->Request()->getQuery();
        if(isset($query['username'])&&$query['username']!='default') {
            $config['username'] = $query['username'];
        }
        if(isset($query['prefix'])&&$query['prefix']!='default') {
            $config['prefix'] = $query['prefix'];
        }
        if(isset($query['password'])&&$query['password']!='default') {
            $config['password'] = $query['password'];
        }
        if(isset($query['host'])&&$query['host']!='default') {
            $config['host'] = $query['host'];
        }
        if(isset($query['port'])&&$query['port']!='default') {
            $config['port'] = $query['port'];
        }
        if(isset($query['database'])&&$query['database']!='default') {
            $config['dbname'] = $query['database'];
        }
        return Shopware_Components_Migration::factory($query['profile'], $config);
    }

    /**
     * Getter function of the source profile
     * @return Shopware_Components_Migration_Profile
     */
    public function Source()
    {
        if(!isset($this->source)) {
            $this->source = $this->initSource();
        }
        return $this->source;
    }

    /**
     * Initial the target profile. The target profile type is every time shopware
     * @return Enlight_Class
     */
    public function initTarget()
    {
        $config = (array) Shopware()->getOption('db');
        return Shopware_Components_Migration::factory('Shopware', $config);
    }


	public function Helpers()
	{
		if (!isset($this->helpers)) {
			$this->helpers = new Shopware_Components_Migration_Helpers();
		}
		return $this->helpers;
	}

    /**
     * Getter method of the target profile. If the profile is not set, the controller initial the profile first.
     * @return Shopware_Components_Migration_Profile
     */
    public function Target()
    {
        if(!isset($this->target)) {
            $this->target = $this->initTarget();
        }
        return $this->target;
    }

    /**
     * Truncates the migration mapping table
     */
    public function clearMigrationMappings()
	{
		$sql = '
            TRUNCATE TABLE `s_plugin_migrations`;
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
		Shopware()->Db()->query($sql, array($type));
	}

    /**
     * Truncate all article related tables
     */
    public function sDeleteAllArticles()
    {
        $sql = "
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
			TRUNCATE s_articles_prices;
			TRUNCATE s_articles_prices_attributes;
			TRUNCATE s_articles_relationships;
			TRUNCATE s_articles_similar;
			TRUNCATE s_articles_translations;
			TRUNCATE s_article_configurator_dependencies;
			TRUNCATE s_article_configurator_groups;
			TRUNCATE s_article_configurator_options;
			TRUNCATE s_article_configurator_option_relations;
			TRUNCATE s_article_configurator_price_surcharges;
			TRUNCATE s_article_configurator_sets;
			TRUNCATE s_article_configurator_set_group_relations;
			TRUNCATE s_article_configurator_set_option_relations;
			TRUNCATE s_article_configurator_templates;
			TRUNCATE s_article_configurator_templates_attributes;
			TRUNCATE s_article_configurator_template_prices;
			TRUNCATE s_article_configurator_template_prices_attributes;
			TRUNCATE s_article_img_mappings;
			TRUNCATE s_article_img_mapping_rules;
        ";
        Shopware()->Db()->query($sql);
    }

    /**
     * Truncate order related tables.
     */
    public function sDeleteAllOrders()
    {
        $sql = "
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
        ";

        Shopware()->Db()->query($sql);
    }

    /**
     * Truncate customer related tables
	 */
	public function sDeleteAllCustomers()
	{
	   $sql = "
	       TRUNCATE s_user;
	       TRUNCATE s_user_attributes;
	       TRUNCATE s_user_billingaddress;
	       TRUNCATE s_user_billingaddress_attributes;
	       TRUNCATE s_user_shippingaddress;
	       TRUNCATE s_user_shippingaddress_attributes;
	       TRUNCATE s_user_shippingaddress_attributes;
	       TRUNCATE s_user_debit;
	   ";

	   Shopware()->Db()->query($sql);
	}

	/**
	 * Helper method to delete all filter properties
	 */
	public function sDeleteAllFilters()
	{
		$sql = '
			TRUNCATE s_filter;
			TRUNCATE s_filter_articles;
			TRUNCATE s_filter_attributes;
			TRUNCATE s_filter_options;
			TRUNCATE s_filter_relations;
			TRUNCATE s_filter_values;
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
			TRUNCATE s_articles_img;
			TRUNCATE s_articles_img_attributes;
			TRUNCATE s_article_img_mappings;
			TRUNCATE s_article_img_mapping_rules;
			TRUNCATE s_media;
		';
		Shopware()->Db()->query($sql);

		$foldersToClean = array(
			Shopware()->DocPath('media/image'),
			Shopware()->DocPath('media/image/thumbnail')
		);

		foreach($foldersToClean as $path) {
			if ($handle = opendir($path)) {
				while (false !== ($file = readdir($handle))) {
					// only delete .jpg, .jpeg, .png and .gif; ignore case
					if (preg_match('/.jpg|.jpeg|.png|.gif/i', $file)) {
						unlink($path.$file);
					}
				}
			}
		}
	}

    /**
     * This function is used to reset the shop. It will truncated all tables related to a given source
     */
	public function clearShopAction()
	{
        $this->Front()->Plugins()->Json()->setRenderer(false);

        // Disable foreign key checks
        Shopware()->Db()->exec("SET foreign_key_checks = 0;");

        // Iterate fields and delete all data
        $data = $this->Request()->getParams();
        foreach ($data as $key => $value) {
            switch ($key) {
                case 'clear_customers':
                    $this->sDeleteAllCustomers();
                    $this->removeMigrationMappingsByType(Shopware_Components_Migration_Helpers::MAPPING_CUSTOMER);
                    break;
                case 'clear_orders':
                    $this->sDeleteAllCustomers();
                    $this->sDeleteAllOrders();
                    $this->removeMigrationMappingsByType(Shopware_Components_Migration_Helpers::MAPPING_CUSTOMER);
                    $this->removeMigrationMappingsByType(Shopware_Components_Migration_Helpers::MAPPING_ORDER);
                    break;
                case 'clear_votes':
                    Shopware()->Db()->exec("TRUNCATE s_articles_vote;");
                    break;
                case 'clear_articles':
                    $this->sDeleteAllArticles();
                    $this->removeMigrationMappingsByType(Shopware_Components_Migration_Helpers::MAPPING_ARTICLE);
                    break;
                case 'clear_categories':
                    Shopware()->Api()->Import()->sDeleteAllCategories();
                    $this->removeMigrationMappingsByType(Shopware_Components_Migration_Helpers::MAPPING_CATEGORY);
                    $this->removeMigrationMappingsByType(Shopware_Components_Migration_Helpers::MAPPING_CATEGORY_TARGET);
                    break;
                case 'clear_supplier':
                    // As one might want to clear the suppliers without leaving all related articles
                    // invalid, we create a new 'Default'-Supplier and set it for all articles
                    Shopware()->Db()->exec("
                        TRUNCATE s_articles_supplier;
                        TRUNCATE s_articles_supplier_attributes;
                        INSERT INTO s_articles_supplier (`id`, `name`) VALUES (1, 'Default');
                        INSERT INTO s_articles_supplier_attributes (`id`) VALUES (1);
                        UPDATE s_articles SET supplierID=1 WHERE 1;
                    ");
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
                default:
                    break;
            }
        }

		echo Zend_Json::encode(array('success'=>true));
	}

    /**
     * Returns the possible migration profiles
     */
    public function profileListAction()
    {
        $this->Front()->Plugins()->Json()->setRenderer(false);

        $rows = array(
            array('id'=>'Magento', 		'name'=>'Magento 1.4.2 bis 1.7.2'),
            array('id'=>'Oxid', 		'name'=>'OXID eShop bis 4.7.1'),
            array('id'=>'Veyton', 		'name'=>'xt:Commerce VEYTON 4.0'),
            array('id'=>'Gambio', 		'name'=>'Gambio GX 2.0.10'),
            array('id'=>'Xt Commerce', 	'name'=>'XTModified & xt:Commerce 3.04'),
            array('id'=>'Shopware35', 	'name'=>'Experimental: Shopware 3.5.7'),
            array('id'=>'PrestaShop', 	'name'=>'PrestaShop 1.5.3'),
        );
        echo Zend_Json::encode(array('data'=>$rows, 'count'=>count($rows)));
    }

    /**
     * Returns the database list of the server.
     */
    public function databaseListAction()
    {
        $this->Front()->Plugins()->Json()->setRenderer(false);

        $rows = array();
        try {
            foreach ($this->Source()->getDatabases() as $database) {
                $rows[] = array('name'=>$database);
            }
        } catch(\Exception $e) {
            $msg = sprintf("An error occured: %s", $e->getMessage());
            echo Zend_Json::encode(array('success'=>false, 'message'=>$msg));
            return;
        }

        echo Zend_Json::encode(array('data'=>$rows, 'count'=>count($rows)));
    }

    /**
     * Helper function to set an automatic mapping when the user open the mapping panel.
     * @param $array
     * @return mixed
     */
    private function setAliases($array) {
        $aliasList = array(
            //Languages - Shops
            array("deutsch", "german", "main store", "main", "mainstore", "hauptshop deutsch"),
            array("englisch", "english", "default english"),
            array("französisch", "french"),

            //Payments
            array("vorkasse", "vorauskasse", "prepayment", "in advance"),

            //order states
            array("in bearbeitung(wartet)", "in bearbeitung", "wird bearbeitet", "bearbeitung", "in progress", "in process", "processing"),
            array("offen", "open", "opened"),
            array("komplett abgeschlossen", "abgeschlossen", "completed", "fully completed", "finish", "finished"),
            array("teilweise abgeschlossen", "partially completed", "partially finished"),
            array("storniert / abgelehnt", "storniert", "abgelehnt", "canceled", "declined", "rejected", "denied"),
            array("zur lieferung bereit", "lieferbereit", "ready for delivery", "ready for deliver", "ready to ship"),
            array("klärung notwendig", "klärung", "mehr informationen notwendig", "clarification needed", "declaration needed", "more information needed"),
            array("abgebrochen", "canceled", "aborted"),

            //taxes
            array("Standardsatz", "standard tax rate", "19%", "19 %"),
            array("ermäßigter Steuersatz", "reduced tax rate", "7%", "7 %")
        );

        foreach($array as &$element) {
            $temp = $element;
            foreach($aliasList as $alias) {
                if(in_array(strtolower($temp), $alias)) {
                    array_unshift($alias, $temp);
                    $element = $alias;
                    break;
                }
            }
        }
        return $array;
    }

    /**
     * This function returns the mapping list for the left grid
     */
    public function mappingListLeftAction()
    {
        $this->Front()->Plugins()->Json()->setRenderer(false);

        $rows = array();

        $target = self::setAliases($this->Target()->getShops());
        $shops = self::mapArrays($this->Source()->getShops(), $target);
        foreach ($shops as $id=>$name) {
            $rows[] = array('internalId'=>$id, 'name'=>$name["value"], 'group'=>'shop', 'mapping_name'=>$name["mapping"], 'mapping'=>$name["mapping_value"], 'required'=>true);
        }

        $target = self::setAliases($this->Target()->getLanguages());
        $languages = self::mapArrays($this->Source()->getLanguages(), $target);
        foreach ($languages as $id=>$name) {
            $rows[] = array('internalId'=>$id, 'name'=>$name["value"], 'group'=>'language', 'mapping_name'=>$name["mapping"], 'mapping'=>$name["mapping_value"], 'required'=>true);
        }

        $target = self::setAliases($this->Target()->getCustomerGroups());
        $customerGroups = self::mapArrays($this->Source()->getCustomerGroups(), $target);
        foreach ($customerGroups as $id=>$name) {
            $rows[] = array('internalId'=>$id, 'name'=>$name["value"], 'group'=>'customer_group', 'mapping_name'=>$name["mapping"], 'mapping'=>$name["mapping_value"], 'required'=>true);
        }

        $target = self::setAliases($this->Target()->getPriceGroups());
        $priceGroups = self::mapArrays($this->Source()->getPriceGroups(), $target);
        foreach ($priceGroups as $id=>$name) {
            $rows[] = array('internalId'=>$id, 'name'=>$name["value"], 'group'=>'price_group', 'mapping_name'=>$name["mapping"], 'mapping'=>$name["mapping_value"]);
        }

        echo Zend_Json::encode(array('data'=>$rows, 'count'=>count($rows)));
    }

    /**
     * Internal helper function for the automatic mapping
     * @param $sourceArray
     * @param $targetArray
     * @return mixed
     */
    private function mapArrays($sourceArray, $targetArray) {
        foreach($sourceArray as &$source) {
            $source = array("value"=> $source, "mapping"=>'', "mapping_value"=>'');
            foreach($targetArray as $key => $target) {
                if(is_array($target)){
                    foreach($target as $alias) {
                        if(strtolower($source["value"]) == strtolower($alias)
                            || (strtolower(substr($source["value"],0,6)) == strtolower(substr($alias,0,6))))
                        {
                            $source["mapping"] = $target[0];
                            $source["mapping_value"] = $key;
                            break;
                        }
                    }
                } else {
                    if(strtolower($source["value"])==strtolower($target)
                        || (strtolower(substr($source["value"],0,6)) == strtolower(substr($target,0,6))))
                    {
                        $source["mapping"] = $target;
                        $source["mapping_value"] = $key;
                        break;
                    }
                }
            }

            if ($source['mapping'] === '' && $source['mapping_value'] === '') {
                $source["mapping"] = 'Bitte wählen';
                $source["mapping_value"] = 'Bitte wählen';
            }

        }
        return $sourceArray;
    }

    /**
     * This function returns the mapping list of the right grid panel
     */
    public function mappingListRightAction()
    {
        $this->Front()->Plugins()->Json()->setRenderer(false);

        $rows = array();

        $target = self::setAliases($this->Target()->getPaymentMeans());
        $paymentMeans = self::mapArrays($this->Source()->getPaymentMeans(), $target);
        foreach ($paymentMeans as $id=>$name) {
            $rows[] = array('internalId'=>$id, 'name'=>$name["value"], 'group'=>'payment_mean', 'mapping_name'=>$name["mapping"], 'mapping'=>$name["mapping_value"]);
        }

        $target = self::setAliases($this->Target()->getOrderStatus());
        $orderStatus = self::mapArrays($this->Source()->getOrderStatus(), $target);
        foreach ($orderStatus as $id=>$name) {
            $rows[] = array('internalId'=>$id, 'name'=>$name["value"], 'group'=>'order_status', 'mapping_name'=>$name["mapping"], 'mapping'=>$name["mapping_value"]);
        }

        $target = self::setAliases($this->Target()->getTaxRates());
        $taxRates = self::mapArrays($this->Source()->getTaxRates(), $target);
        foreach ($taxRates as $id=>$name) {
            $rows[] = array('internalId'=>$id, 'name'=>$name["value"], 'group'=>'tax_rate', 'mapping_name'=>$name["mapping"], 'mapping'=>$name["mapping_value"]);
        }

        $target = self::setAliases($this->Target()->getAttributes());
        $attributes = self::mapArrays($this->Source()->getAttributes(), $target);
        foreach ($attributes as $id=>$name) {
            $rows[] = array('internalId'=>$id, 'name'=>$name["value"], 'group'=>'attribute', 'mapping_name'=>$name["mapping"], 'mapping'=>$name["mapping_value"]);
        }

		$target = self::setAliases($this->Target()->getProperties());
		$attributes = self::mapArrays($this->Source()->getProperties(), $target);
		foreach ($attributes as $id=>$name) {
			$rows[] = array('internalId'=>$id, 'name'=>$name["value"], 'group'=>'property_options', 'mapping_name'=>$name["mapping"], 'mapping'=>$name["mapping_value"]);
		}

        $target = self::setAliases(sort($this->Target()->getConfiguratorOptions()));
        $attributes = self::mapArrays($this->Source()->getConfiguratorOptions(), $target);
        ksort($attributes);
        foreach ($attributes as $id=>$name) {
            $rows[] = array('internalId'=>$id, 'name'=>$name["value"], 'group'=>'configurator_mapping', 'mapping_name'=>$name["mapping"], 'mapping'=>$name["mapping_value"]);
        }

        echo Zend_Json::encode(array('data'=>$rows, 'count'=>count($rows)));
    }

    /**
     * This function returns the values for the grid combo boxes
     */
    public function mappingValuesListAction()
    {
        $this->Front()->Plugins()->Json()->setRenderer(false);

        switch ($this->Request()->mapping) {
            case 'shop':
                $values = $this->Target()->getShops();
                break;
            case 'language':
                $values = $this->Target()->getLanguages();
                break;
            case 'customer_group':
                $values = $this->Target()->getCustomerGroups();
                break;
            case 'price_group':
                $values = $this->Target()->getPriceGroups();
                break;
            case 'payment_mean':
                $values = $this->Target()->getPaymentMeans();
                break;
            case 'order_status':
                $values = $this->Target()->getOrderStatus();
                break;
            case 'tax_rate':
                $values = $this->Target()->getTaxRates();
                break;
            case 'attribute':
                $values = $this->Target()->getAttributes();
                break;
	        case 'property_options':
		        $values = $this->Target()->getProperties();
		        break;
            case 'configurator_mapping':
   		        $values = $this->Target()->getConfiguratorOptions();
   		        break;
            default:
                break;
        }

	    // The id is not needed later - it just may not collide with any other id
	    $rows = array(array('id'=>'Bitte wählen', 'name'=>'Bitte wählen'));


        if(!empty($values)) {
            foreach ($values as $key=>$value) {
                $rows[] = array('id'=>$key, 'name'=>$value);
            }
        }
        echo Zend_Json::encode(array('data'=>$rows, 'count'=>count($rows)));
    }

    /**
     * This function validate the first form panel
     */
    public function checkFormAction()
    {
        $this->namespace = Shopware()->Snippets()->getNamespace('backend/swag_migration/main');
        $this->Front()->Plugins()->Json()->setRenderer(false);

        try {
            $shops = $this->Source()->getShops();
            $languages = $this->Source()->getLanguages();
            //$image_path = rtrim($this->Request()->basepath.$this->Source()->getProductImagePath(), '/').'/';
            //$client = new Zend_Http_Client($image_path);
            echo Zend_Json::encode(array('success'=>true));

        } catch (Zend_Db_Statement_Exception $e) {
            switch($e->getCode()) {
                case 42:
                    echo Zend_Json::encode(array('success'=>false, 'message'=>$this->namespace->get('databaseProfileDoesNotMatch', "The selected profile does not match the selected database. Please make sure that the selected database is the database you want to import.")));
                    break;
                default:
                    echo Zend_Json::encode(array('success'=>false, 'message'=>$e->getMessage()));
            }
        } catch (Exception $e) {
            echo Zend_Json::encode(array('success'=>false, 'message'=>$e->getMessage()));
        }
    }

    /**
     * Helper function to format image names the way the media resource expects it
     *
     * @param $name
     * @return string
     */
    private function removeSpecialCharacters($name)
    {
        $name = iconv('utf-8', 'ascii//translit', $name);
        $name = strtolower($name);
        $name = preg_replace('#[^a-z0-9\-_]#', '-', $name);
        $name = preg_replace('#-{2,}#', '-', $name);
        $name = trim($name, '-');
        return mb_substr($name, 0, 180);
    }

    /**
     * Takes an invalid product number and creates a valid one from it
     * by returning its md5 hash
     *
     * todo: Generate more readable ordernumbers
     */
    public function makeInvalidNumberValid($number, $id)
    {
        // Look up the id in the database - perhaps we've already created a valid number:
        $number = Shopware()->Db()->fetchOne(
            'SELECT targetID FROM s_plugin_migrations WHERE typeID = ? AND sourceID = ?',
            array(Shopware_Components_Helpers::MAPPING_VALID_NUMBER, $id)
        );

        if ($number) {
            return $number;
        }

        // Get number
        $number = (int) Shopware()->Db()->fetchOne(
            'SELECT `number` FROM `s_order_number` WHERE `name`="articleordernumber" FOR UPDATE'
        );

        // Increase - save
        Shopware()->Db()->update(
            's_order_number',
            array('number' => ++$number),
            array('name' => 'articleordernumber')
        );

        // Save mapping
        Shopware()->Db()->insert(
            's_plugin_migrations',
            array(
                'typeID' => Shopware_Components_Helpers::MAPPING_VALID_NUMBER,
                'sourceID' => $id,
                'targetID' => $number
            )
        );

        return 'sw-'.$number;

//        return "sw-".md5($id);
    }

    /**
     * This function finish the import and truncate the plugin table.
     */
    public function finishImport()
    {
        $this->clearMigrationMappings();
        echo Zend_Json::encode(array(
            'message'=>$this->namespace->get('importFinished', "Import finished"),
            'success'=>true,
            'progress'=>1,
            'done'=>true
        ));
    }

    /**
     * Convenience method which prints a given error for the extjs app
     * @param $e
     * @param $errorDescription A simple explanation of what happened
     */
    protected function printError($e, $errorDescription)
    {
        $error = array(
            'message'=>$errorDescription,
            'error'=>$e->getMessage(),
            'code'=>$e->getCode(),
            'file'=>$e->getFile(),
            'line'=>$e->getLine(),
            'trace'=>$e->getTraceAsString(),
            'success'=>false,
            'progress'=>1,
            'done'=>true
        );
        if (!$this->Front()->Plugins()->Json()->getRenderer()) {
            echo Zend_Json::encode($error);
        } else {
            $this->view->assign($error);
        }
    }

    /**
     * Triggers the actual import for a given type
     *
     *
     * @param $importType
     */
    public function runImport($importType)
    {
        $offset = empty($this->Request()->offset) ? 0 : (int) $this->Request()->offset;
        $name = $this->imports[$importType];

        if ($this->printCurrentImportMessage($name)) {
            return;
        }

        /** @var $progress Shopware_Components_Migration_Import_Progress */
        $progress = new Shopware_Components_Migration_Import_Progress();
        $progress->setOffset($offset);


        /** @var $import Shopware_Components_Migration_Import_Base */
        $className = 'Shopware_Components_Migration_Import_' . $name;
        $import = Enlight_Class::Instance($className, array(
            $progress,
            $this->Source(), $this->Target(), $this->max_execution, $this->Request()
        ));

        $import->setInternalName($importType);

        try {
            $retProgress = $import->run();
            if ($retProgress) {
                $progress = $retProgress;
            }
        } catch (Exception $e) {
            $this->printError($e, $import->getDefaultErrorMessage());
            return;
        }

        $progress->addRequestParam('messageShown', 0);

        if ($progress->isDone()) {
            // Set the current import type to null so that it won't be triggered again
            $progress->addRequestParam($importType, null);
            $progress->setMessage($import->getDoneMessage());
        } elseif (!$progress->isError()) {
            // Default "progress" action
            $progress->setMessage($import->getCurrentProgressMessage($progress));
            $progress->setSuccess(true);
        }

        // Print the json formatted output
        $progress->printOutput();
    }

    /**
     * This function imports the different data types.
     * @return void
     */
    public function importAction()
    {
        $this->namespace = Shopware()->Snippets()->getNamespace('backend/swag_migration/main');
        $this->Front()->Plugins()->Json()->setRenderer(false);

        foreach ($this->imports as $key => $name) {
            if(!empty($this->Request()->$key)) {
                $this->runImport($key);
                return;
            }
        }

        if(!empty($this->Request()->finish_import)) {
            return $this->finishImport();
        }

        echo Zend_Json::encode(array(
            'message'=>$this->namespace->get('importedSelectedData', "Selected data successfully imported!"),
            'success'=>true,
            'progress'=>1,
            'done'=>true
        ));
    }

    /**
     * Helper method to print "currently importing" messages
     * @param $type
     * @return bool
     */
    public function printCurrentImportMessage($type)
    {
        $offset = empty($this->Request()->offset) ? 0 : (int) $this->Request()->offset;
        if ($offset > 0) {
            return false;
        }
        $messageShown = empty($this->Request()->messageShown) ? false : (bool) $this->Request()->messageShown;
        if ($messageShown) {
            return false;
        }
        $import = $this->namespace->get("currentImport".$type, $type);

        echo Zend_Json::encode(array(
            'message'=>sprintf($this->namespace->get('currentlyImporting', "Current import step: %s"), $import),
            'success'=>true,
            'offset'=>0,
            'progress'=>0,
            'messageShown'=>1
        ));
        return true;
    }

	/**
	 * Helper function which sets the start time for a given task
	 *
	 * Timer should be inited after the inital DB-Query of each task, as this
	 * query will usually take longer on the first run and therefore distort the results
	 *
	 * @return int
	 */
	public function initTaskTimer()
	{
		$startTime = (int) $this->Request()->getParam('task_start_time', 0);
		$offset = empty($this->Request()->offset) ? 0 : (int) $this->Request()->offset;

		if ($startTime == 0 || $offset == 0) {
			$startTime = time();
		}

		$this->Request()->setParam('task_start_time', $startTime);
		return $startTime;
	}

}
