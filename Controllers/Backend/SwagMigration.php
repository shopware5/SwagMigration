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
     * This function add the template directory and register the Shopware_Components namespace
     */
    public function init()
    {
        Shopware()->Loader()->registerNamespace('Shopware_Components', dirname(__FILE__).'/../../Components/');
        $this->View()->addTemplateDir(dirname(__FILE__) . "/../../Views/");
        parent::init();
    }

    /**
     * This function initial the source profile and creates it over the profile factory
     * @return Enlight_Class
     */
    public function initSource()
    {
        $config = (array) Shopware()->getOption('db');
        // Setting the current shopware database as default will fail,
        // if the user wants to connect to a remote database
        $config['dbname'] = "";

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


	public function clearMigrationMappings()
	{
		$sql = '
            TRUNCATE TABLE `s_plugin_migrations`;
        ';
        Shopware()->Db()->query($sql);
	}

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
        TRUNCATE s_order_number;
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
//            array('id'=>'Shopware350', 	'name'=>'Shopware 3.5.0'),
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
     * This function imports the product prices, selected by the source profile product price query
     */
    public function importProductPrices()
    {
        $requestTime = !empty($_SERVER['REQUEST_TIME']) ? $_SERVER['REQUEST_TIME'] : time();
        $offset = empty($this->Request()->offset) ? 0 : (int) $this->Request()->offset;

        if ($this->printCurrentImportMessage('Product prices')) {
            return;
        }

	    // Reset block-prices on the first request. (SW-5471)
	    if ($offset === 0) {
		    $sql = '
			DELETE FROM s_articles_prices WHERE `from` > 1;
		    UPDATE s_articles_prices SET `to` = "beliebig"
		    ';
		    Shopware()->Db()->query($sql);
	    }

        $result = $this->Source()->queryProductPrices($offset);
        $count = $result->rowCount()+$offset;

	    $taskStartTime  = $this->initTaskTimer();

        while ($price = $result->fetch()) {
            if(!empty($this->Request()->price_group) && !empty($price['pricegroup'])) {
                if(isset($this->Request()->price_group[$price['pricegroup']])) {
                    $price['pricegroup'] = $this->Request()->price_group[$price['pricegroup']];
                } else {
                    continue;
                }
            }
            if(empty($price['pricegroup'])) {
                $price['pricegroup'] = 'EK';
            }

            $sql = "
                SELECT ad.id as articledetailsID, IF(cg.taxinput=1, t.tax, 0) as tax
                FROM s_plugin_migrations pm
                JOIN s_articles_details ad
                ON ad.id=pm.targetID
                JOIN s_articles a
                ON a.id=ad.articleID
                JOIN s_core_tax t
                ON t.id=a.taxID
                INNER JOIN s_core_customergroups cg
                ON cg.mode=0
                AND cg.groupkey=?
                WHERE pm.sourceID=?
                AND pm.typeID=?
            ";
            $price_config = Shopware()->Db()->fetchRow($sql, array($price['pricegroup'], $price['productID'], Shopware_Components_Migration_Helpers::MAPPING_ARTICLE));
            if(!empty($price_config)) {
                $price = array_merge($price, $price_config);
                if(isset($price['net_price'])) {
                    if(empty($price['tax'])) {
                        $price['price'] = $price['net_price'];
                        unset($price['net_price'], $price['tax']);
                    } else {
                        $price['price'] = round($price['net_price']*(100+$price['tax'])/100, 2);
                        unset($price['net_price']);
                    }
                }
                $price['articlepricesID'] = Shopware()->Api()->Import()->sArticlePrice($price);
            }


            $offset++;
            if(time()-$requestTime >= 10) {
                echo Zend_Json::encode(array(
                    'message'=>sprintf($this->namespace->get('progressPrices', "%s out of %s prices imported"), $offset, $count),
                    'success'=>true,
                    'offset'=>$offset,
                    'progress'=>$offset/$count,
	                'estimated' => (time()-$taskStartTime)/$offset * ($count-$offset),
                    'task_start_time' => $taskStartTime
                ));
                return;
            }
        }
        echo Zend_Json::encode(array(
            'message'=>$this->namespace->get('importedPrices', "Prices successfully imported!"),
            'success'=>true,
            'import_prices'=>null,
            'offset'=>0,
            'progress'=>-1
        ));
    }

	/**
	 * Copy the article images from the source shop system into the shopware image path
	 * @return
	 */
    public function importProductImages()
    {
        $requestTime = !empty($_SERVER['REQUEST_TIME']) ? $_SERVER['REQUEST_TIME'] : time();
        $offset = empty($this->Request()->offset) ? 0 : (int) $this->Request()->offset;

        if ($this->printCurrentImportMessage('Product images')) {
            return;
        }

        $result = $this->Source()->queryProductImages($offset);

	    $taskStartTime  = $this->initTaskTimer();
        $count = $result->rowCount()+$offset;
        $image_path = rtrim($this->Request()->basepath, '/') . '/' . $this->Source()->getProductImagePath();

        while ($image = $result->fetch()) {

            $image['link'] = $image_path.$image['image'];
			if (!isset($image['name'])) {
				$image['name'] = basename($image['image']);
			}

            $sql = '
                SELECT ad.articleID
                FROM s_plugin_migrations pm
                JOIN s_articles_details ad
                ON ad.id=pm.targetID
                WHERE pm.`sourceID`=?
                AND `typeID`=?
            ';
            $image['articleID'] = Shopware()->Db()->fetchOne($sql, array($image['productID'], Shopware_Components_Migration_Helpers::MAPPING_ARTICLE));

            $sql = '
                SELECT ad.articleID, ad.ordernumber, ad.kind
                FROM s_plugin_migrations pm
                JOIN s_articles_details ad
                ON ad.id=pm.targetID
                WHERE pm.`sourceID`=?
                AND `typeID`=?
            ';
            $product_data = Shopware()->Db()->fetchRow($sql, array($image['productID'], Shopware_Components_Migration_Helpers::MAPPING_ARTICLE));

            if(!empty($product_data)) {
	            if ($this->Source()->checkForDuplicateImages()) {
		            if ($this->Helpers()->imageAlreadyImported($product_data['articleID'], $image['link'])) {
						$offset++;
			            continue;
		            }
	            }

                if(!empty($image['main']) && $product_data['kind']==1) {
                    Shopware()->Api()->Import()->sDeleteArticleImages(array('articleID'=>$product_data['articleID']));
                }
                $image['articleID'] = $product_data['articleID'];
                if($product_data['kind']==2) {
                    $image['relations'] = $product_data['ordernumber'];
                }
                $image['articleimagesID'] = Shopware()->Api()->Import()->sArticleImage($image);
            }

            $offset++;

            if(time()-$requestTime >= 10) {
                echo Zend_Json::encode(array(
                    'message'=>sprintf($this->namespace->get('progressImages', "%s out of %s images imported"), $offset, $count),
                    'success'=>true,
                    'offset'=>$offset,
                    'progress'=>$offset/$count,
	                'estimated' => (time()-$taskStartTime)/$offset * ($count-$offset),
                    'task_start_time' => $taskStartTime
                ));
                return;
            }
        }
        echo Zend_Json::encode(array(
            'message'=>$this->namespace->get('importedImages', "Images successfully imported!"),
            'success'=>true,
            'import_images'=>null,
            'offset'=>0,
            'progress'=>-1
        ));
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

        Shopware()->Db()->query($sql, array(Shopware_Components_Migration_Helpers::MAPPING_CATEGORY_TARGET, $id, $target));
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
            array(Shopware_Components_Migration_Helpers::MAPPING_CATEGORY_TARGET, $id)
        );
    }

    /**
     * Delete category target
     * @param $id
     */
    public function deleteCategoryTarget($id)
    {
        $sql = "DELETE FROM s_plugin_migrations WHERE typeID = ? AND sourceID = '{$id}'";
        Shopware()->Db()->query($sql, array(Shopware_Components_Migration_Helpers::MAPPING_CATEGORY_TARGET));
    }

    /**
     * Imports the categories of the target database into the shopware database
     * Some shop system have only one main shop. For this shops the categories translations will split into new categories.
     *
     * @return void
     */
    public function importCategories()
    {
        $requestTime = !empty($_SERVER['REQUEST_TIME']) ? $_SERVER['REQUEST_TIME'] : time();
        $offset = empty($this->Request()->offset) ? 0 : (int) $this->Request()->offset;

        if ($this->printCurrentImportMessage('Categories')) {
            return;
        }

        $skip = false;

		// Cleanup previous category imports
        if (!$skip && $offset === 0) {
            Shopware()->Db()->query("DELETE FROM s_plugin_migrations WHERE typeID IN (?, ?);",
	            array(Shopware_Components_Migration_Helpers::MAPPING_CATEGORY_TARGET,2)
            );
        }

        $categories = $this->Source()->queryCategories($offset);
        $count = $categories->rowCount()+$offset;

	    $taskStartTime  = $this->initTaskTimer();

        while (!$skip && $category = $categories->fetch()) {
			//check if the category split into the different translations
            if(!empty($category['languageID'])&& strpos($category['categoryID'], '_')===false) {
                $category['categoryID'] = $category['categoryID'].'_'.$category['languageID'];

				if(!empty($category['parentID'])) {
                    $category['parentID'] = $category['parentID'].'_'.$category['languageID'];
                }
            }

            $target_parent = $this->getCategoryTarget($category['parentID']);

            // Do not create empty categories
            if(empty($category['description'])) {
                $offset++;
                continue;
            }

            if(!empty($category['parentID'])) {
                // Map the category IDs
                if (false !== $target_parent) {
                    $category['parent'] = $target_parent;
                } else {
                    error_log("Parent category not found");
                    $offset++;
                    continue;
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

            Shopware()->Db()->query($sql , array(Shopware_Components_Migration_Helpers::MAPPING_CATEGORY, $category['categoryID'], $category['targetID']));

            $offset++;
            if(time()-$requestTime >= 10) {
                echo Zend_Json::encode(array(
                    'message'=>sprintf($this->namespace->get('progressCategories', "%s out of %s categories imported"), $offset, $count),
                    'success'=>true,
                    'offset'=>$offset,
                    'progress'=>$offset/$count,
	                'estimated' => (time()-$taskStartTime)/$offset * ($count-$offset),
                    'task_start_time' => $taskStartTime
                ));
                return;
            }

        }

        echo Zend_Json::encode(array(
            'message'=>$this->namespace->get('importedCategories', "Categories successfully imported!"),
            'success'=>true,
            'import_categories'=>null,
            'import_article_categories'=>1,
            'offset'=>0,
            'progress'=>-1
        ));

    }

    /**
     * Import Article-Category assignments
     */
    public function importArticleCategories()
    {
        $requestTime = !empty($_SERVER['REQUEST_TIME']) ? $_SERVER['REQUEST_TIME'] : time();
        $offset = empty($this->Request()->offset) ? 0 : (int) $this->Request()->offset;

        if ($this->printCurrentImportMessage('Article-Category assignment')) {
            return;
        }

        $result = $this->Source()->queryProductCategories($offset);

        $count = $result->rowCount()+$offset;

	    $taskStartTime  = $this->initTaskTimer();

        while ($productCategory = $result->fetch()) {
            if(time()-$requestTime >= 10) {
                echo Zend_Json::encode(array(
                    'message'=>sprintf($this->namespace->get('progressArticleCategories', "%s out of %s articles assigned to categories"), $offset, $count),
                    'success'=>true,
                    'offset'=>$offset,
                    'progress'=>$offset/$count,
	                'estimated' => (time()-$taskStartTime)/offset * ($count-$offset),
                    'task_start_time' => $taskStartTime
                ));
                return;
            }
            $offset++;

            $sql = '
                SELECT ad.articleID
                FROM s_plugin_migrations pm
                JOIN s_articles_details ad
                ON ad.id=pm.targetID
                WHERE `sourceID`=?
                AND `typeID`=?
            ';
            $article = Shopware()->Db()->fetchOne($sql , array($productCategory['productID'], Shopware_Components_Migration_Helpers::MAPPING_ARTICLE));

            if(empty($article)) {
                continue;
            }

            $sql = '
                SELECT `targetID`
                FROM `s_plugin_migrations`
                WHERE `typeID`=? AND (`sourceID`=? OR `sourceID` LIKE ?)
            ';
            // Also take language categories into account
            $categories = Shopware()->Db()->fetchCol($sql , array(Shopware_Components_Migration_Helpers::MAPPING_CATEGORY, $productCategory['categoryID'], $productCategory['categoryID'].'_%'));

            if(empty($categories)) {
                continue;
            }

            foreach ($categories as $category) {
                Shopware()->Api()->Import()->sArticleCategory($article, $category, false);
			}
		}

        // Fallback for SW versions prior 4.0.6
        Shopware()->Db()->exec('
            DELETE ac FROM s_articles_categories ac
            INNER JOIN s_categories c ON ac.categoryID =c.id WHERE c.`right`-c.`left` > 1
        ');


        echo Zend_Json::encode(array(
            'message'=>$this->namespace->get('importedCategories', "Categories successfully imported!"),
            'success'=>true,
            'import_categories'=>null,
            'import_article_categories'=>null,
            'offset'=>0,
            'progress'=>-1
        ));
    }

    /**
     * This function import the product ratings, selected by the source profile
     * todo@dn: make this multi request capable
     */
    public function importProductRatings()
    {

        if ($this->printCurrentImportMessage('Product ratings')) {
            return;
        }

        $result = $this->Source()->queryProductRatings();
        while ($rating = $result->fetch()) {
            $sql = '
                SELECT ad.articleID
                FROM s_plugin_migrations pm
                JOIN s_articles_details ad
                ON ad.id=pm.targetID
                WHERE pm.`sourceID`=?
                AND `typeID`=?
            ';
            $rating['articleID'] = Shopware()->Db()->fetchOne($sql, array($rating['productID'], Shopware_Components_Migration_Helpers::MAPPING_ARTICLE));

            if(empty($rating['articleID'])) {
                continue;
            }

            $sql = '
                SELECT `id`
                FROM `s_articles_vote`
                WHERE `articleID`=?
                AND `name` LIKE ?
                AND `email`=?
            ';
            $ratingID = Shopware()->Db()->fetchOne($sql, array(
                $rating['articleID'],
                $rating['name'],
                !empty($rating['email']) ? $rating['email'] : ''
            ));

            if(!empty($ratingID)) {
                continue;
            }

            $data = array(
                'articleID' => $rating['articleID'],
                'name' => !empty($rating['name']) ? $rating['name'] : '',
                'headline' => !empty($rating['title']) ? $rating['title'] : '',
                'comment' => !empty($rating['comment']) ? $rating['comment'] : '',
                'points' =>  isset($rating['rating']) ? (float) $rating['rating'] : 5,
                'datum' => isset($rating['date']) ? $rating['date'] : new Zend_Db_Expr('NOW()'),
                'active' => isset($rating['active']) ? $rating['active'] : 1,
                'email' => !empty($rating['email']) ? $rating['email'] : '',
            );
            Shopware()->Db()->insert('s_articles_vote', $data);

        }
        echo Zend_Json::encode(array(
            'message'=>$this->namespace->get('importedRatings', "Ratings successfully imported!"),
            'success'=>true,
            'import_ratings'=>null,
            'offset'=>0,
            'progress'=>-1
        ));
    }

	/**
	 * Imports the product translation from the source database into the shopware database
	 */
    public function importProductTranslations()
    {
        $requestTime = !empty($_SERVER['REQUEST_TIME']) ? $_SERVER['REQUEST_TIME'] : time();
        $offset = empty($this->Request()->offset) ? 0 : (int) $this->Request()->offset;

        if ($this->printCurrentImportMessage('Product translations')) {
            return;
        }

        $result = $this->Source()->queryProductTranslations($offset);
        $count = $result->rowCount()+$offset;

	    $taskStartTime  = $this->initTaskTimer();

        while ($translation = $result->fetch()) {

            //Attribute
            if(!empty($this->Request()->attribute)) {
                foreach ($this->Request()->attribute as $source=>$target) {
                    if(!empty($target) && isset($translation[$source])) {
                        $translation[$target] = $translation[$source];
                        unset($translation[$source]);
                    }
                }
            }

            //set the language id of the translation
            if(isset($this->Request()->language[$translation['languageID']])) {
                $translation['languageID'] = $this->Request()->language[$translation['languageID']];
            }

            //get the product data
            $sql = '
                SELECT ad.articleID, ad.id as articledetailsID, kind
                FROM s_plugin_migrations pm
                JOIN s_articles_details ad
                ON ad.id=pm.targetID
                WHERE pm.`sourceID`=?
                AND `typeID`=?
            ';
            $product_data = Shopware()->Db()->fetchRow($sql, array($translation['productID'], Shopware_Components_Migration_Helpers::MAPPING_ARTICLE));

            if(!empty($product_data)) {
                $translation['articletranslationsID'] = Shopware()->Api()->Import()->sTranslation(
                    $product_data['kind']==1 ? 'article' : 'variant',
                    $product_data['kind']==1 ? $product_data['articleID'] : $product_data['articledetailsID'],
                    $translation['languageID'],
                    $translation
                );
            }

            $offset++;
            if(time()-$requestTime >= 10)  {
                echo Zend_Json::encode(array(
                    'message'=>sprintf($this->namespace->get('progressTranslations', "%s out of %s translations imported"), $offset, $count),
                    'success'=>true,
                    'offset'=>$offset,
                    'progress'=>$offset/$count,
	                'estimated' => (time()-$taskStartTime)/$offset * ($count-$offset),
                    'task_start_time' => $taskStartTime
                ));
                return;
            }
        }
        echo Zend_Json::encode(array(
            'message'=>$this->namespace->get('importedTranslations', "Translations successfully imported!"),
            'success'=>true,
            'import_translations'=>null,
            'offset'=>0,
            'progress'=>-1
        ));
    }

    /**
     * Takes an invalid product number and creates a valid one from it
     * by returning its md5 hash
     */
    public function makeInvalidNumberValid($number, $id)
    {
        return "sw-".md5($id);
    }

    /**
     * This function imports the products, selected by the source profile. For the import the shopware api import used.
     * @return mixed
     */
    public function importProducts()
    {
        $requestTime = !empty($_SERVER['REQUEST_TIME']) ? $_SERVER['REQUEST_TIME'] : time();
        $offset = empty($this->Request()->offset) ? 0 : (int) $this->Request()->offset;
        $numberValidationMode = $this->Request()->getParam('number_validation_mode', 'complain');

        $import = Shopware()->Api()->Import();

        if ($this->printCurrentImportMessage('Products')) {
            return;
        }

        $result = $this->Source()->queryProducts($offset);
        $count = $result->rowCount()+$offset;

	    $taskStartTime  = $this->initTaskTimer();

        $numberSnippet = $this->namespace->get('numberNotValid',
            "The product number %s is not valid. A valid product number must:<br>
            * not be longer than 40 chars<br>
            * not contain other chars than: 'a-zA-Z0-9-_.' and SPACE<br>
            <br>
            You can force the migration to continue. But be aware that this will: <br>
            * Truncate ordernumbers longer than 40 chars and therefore result in 'duplicate keys' exceptions <br>
            * Will not allow you to modify and save articles having an invalid ordernumber <br>
            ");

        while ($product = $result->fetch()) {
            // Select additional data for the article if needed
            $additionalProductInfo = $this->Source()->getAdditionalProductInfo($product['productID']);
            if (!empty($additionalProductInfo)) {
                // Merge the results with the pre-existing product array
                $product = array_merge($product, $additionalProductInfo);
            }

            // Check the ordernumber
            $number = $product['ordernumber'];
            if ($numberValidationMode !== 'ignore' && isset($number) &&
                (strlen($number) > 40 || preg_match('/[^a-zA-Z0-9-_. ]/', $number)))
            {
                switch ($numberValidationMode) {
                    case 'complain':
                        echo Zend_Json::encode(array(
                            'message'=>sprintf($numberSnippet, $number),
                            'success'=>false,
                            'import_products'=>null,
                            'offset'=>0,
                            'progress'=>-1
                        ));
                        return;
                        break;
                    case 'make_valid':
                        $product['ordernumber'] = $this->makeInvalidNumberValid($number, $product['productID']);
                        break;
                }
            }


            //Attribute
            if(!empty($this->Request()->attribute)) {
                foreach ($this->Request()->attribute as $source=>$target) {
                    if(!empty($target) && isset($product[$source])) {
                        $product[$target] = $product[$source];
                        unset($product[$source]);
                    }
                }
            }
            //TaxRate
            if(!empty($this->Request()->tax_rate) && isset($product['taxID'])) {
                if(isset($this->Request()->tax_rate[$product['taxID']])) {
                    $product['taxID'] = $this->Request()->tax_rate[$product['taxID']];
                } else {
                    unset($product['taxID']);
                }
            }
            //Supplier
            if(empty($product['supplierID']) && empty($product['supplier'])) {
                $product['supplier'] = $this->Request()->supplier;
            }
            //Parent
            if(!empty($product['parentID'])) {
                $sql = 'SELECT `targetID` FROM `s_plugin_migrations` WHERE `typeID`=? AND `sourceID`=?';
                $product['maindetailsID'] = Shopware()->Db()->fetchOne($sql , array(Shopware_Components_Migration_Helpers::MAPPING_ARTICLE, $product['parentID']));
            }

            if(isset($product['description_long'])) {
                $product_description = $product['description_long'];
                unset($product['description_long']);
            } else {
                $product_description = null;
            }

            if(isset($product['description'])) {
                $product['description'] = strip_tags($product['description']);
            }

            //Article
            $product_result = $import->sArticle($product);
            if(!empty($product_result)) {
                $product = array_merge($product, $product_result);

	            /**
	             * Check if the parent article's detail has configurator options associated
	             *
	             * If this is not the case, it was a dummy master article in the source system and
	             * needs to be replaced by another variant
	             */
	            if ($product['maindetailsID']) {
					// Get options of the old main detail
					$sql = 'SELECT id FROM s_article_configurator_option_relations WHERE article_id = ?';
					$hasOptions = Shopware()->Db()->fetchOne($sql, array($product['maindetailsID']));

					// If non is available remove the odl detail and set the new one as main detail
					if (!$hasOptions) {
						$this->Helpers()->replaceProductDetail(
							$product['maindetailsID'],
							$product['articledetailsID'],
							$product['articleID']
						);
					}
				}

	            // In some cases we need to make sure, that the article configurator is
	            // generated for the master article of master/child article architectures
	            if (isset($product['masterWithAttributes']) && $product['masterWithAttributes'] == 1) {
		            $product['maindetailsID'] = $product['articledetailsID'];
		            $import->sArticleLegacyVariant($product);
	            }

                if($product['kind']==1 && $product_description!==null) {
                    Shopware()->Db()->update(
                        's_articles',
                        array('description_long'=>$product_description),
                        array('id=?'=>$product_result['articleID'])
                    );
                }




                //Price
                if(isset($product['net_price'])) {
                    if(empty($product['tax'])) {
                        $product['price'] = $product['net_price'];
                        unset($product['net_price'], $product['tax']);
                    } else {
                        $product['price'] = round($product['net_price']*(100+$product['tax'])/100, 2);
                        unset($product['net_price']);
                    }
                }
                if(isset($product['price'])) {
                    $product['articlepricesID'] = $import->sArticlePrice($product);
                }
                //Link
                if(isset($product['link'])) {
                    $import->sDeleteArticleLinks($product);
                    if(!empty($product['link'])) {
                        $product['articlelinkID'] = $import->sArticleLink(array(
                            'articleID' => $product['articleID'],
                            'link' => $product['link'],
                            'description' => empty($product['link_description']) ? $product['link'] : $product['link_description']
                        ));
                    }
                }


                $sql = '
                    INSERT INTO `s_plugin_migrations` (`typeID`, `sourceID`, `targetID`)
                    VALUES (?, ?, ?)
                    ON DUPLICATE KEY UPDATE `targetID`=VALUES(`targetID`);
                ';
                Shopware()->Db()->query($sql , array(Shopware_Components_Migration_Helpers::MAPPING_ARTICLE, $product['productID'], $product['articledetailsID']));
            }

            $offset++;
            if(time()-$requestTime >= 10) {
                echo Zend_Json::encode(array(
                    'message'=>sprintf($this->namespace->get('progressProducts', "%s out of %s products imported"), $offset, $count),
                    'success'=>true,
                    'offset'=>$offset,
                    'progress'=>$offset/$count,
                    'estimated' => (time()-$taskStartTime)/$offset * ($count-$offset),
                    'task_start_time' => $taskStartTime
                ));
                return;
            }
        }
        echo Zend_Json::encode(array(
            'message'=>$this->namespace->get('importedProducts', "Products successfully imported!"),
            'success'=>true,
            'import_products'=>null,
            'offset'=>0,
            'progress'=>-1
        ));
    }

    /**
     * Returns a SW-productID for a given source-productId
     * @param $productId
     * @return string
     */
    public function getBaseArticleInfo($productId)
    {
        $sql = '
            SELECT
                ad.articleID as productId
            FROM s_plugin_migrations pm
            LEFT JOIN s_articles_details ad
                ON ad.id = pm.targetID
            WHERE pm.`sourceID`=?
            AND `typeID`=?
        ';

        return Shopware()->Db()->fetchOne($sql, array($productId, Shopware_Components_Migration_Helpers::MAPPING_ARTICLE));
    }

    /**
     * Generates configuratorSets, configuratorOptions and configuratorGroups
     * for a given article depending on its attributes
     */
    public function generateConfiguratorSetsFromAttributes()
    {

        $requestTime = !empty($_SERVER['REQUEST_TIME']) ? $_SERVER['REQUEST_TIME'] : time();
        $offset = empty($this->Request()->offset) ? 0 : (int) $this->Request()->offset;

        $products_result = $this->Source()->queryAttributedProducts($offset);
        if (empty($products_result)) {
            echo Zend_Json::encode(array(
                'message'=>$this->namespace->get('generatedConfigurators', "Configurators successfully generated!"),
                'success'=>true,
                'import_generate_variants'=>null,
                'import_create_configurator_variants' => null,
                'offset'=>0,
                'progress'=>-1
            ));
            return;
        }

        $count = $products_result->rowCount()+$offset;

	    $taskStartTime  = $this->initTaskTimer();

        // iterate all products with attributes
        while ($product = $products_result->fetch()) {
            $id = $product['productID'];

            // Skip products which have not been imported before
            $productId = $this->getBaseArticleInfo($id);
            if (false === $productId) {
                continue;
            }

            // Create configurator set for product
            $configuratorSetName = "Generated Set - ".$id;
            $configuratorSetId = Shopware()->Db()->fetchOne("
                SELECT `id`
                FROM `s_article_configurator_sets`
                WHERE `name`='{$configuratorSetName}' LIMIT 1
            ");
            if(false === $configuratorSetId) {
                $sql = "INSERT INTO s_article_configurator_sets SET `name`='{$configuratorSetName}'";
                Shopware()->Db()->query($sql);
                $configuratorSetId = Shopware()->Db()->lastInsertId();
            }

            // Get all attributes of the current product
            $result = $this->Source()->queryProductAttributes($id);

            $options = array();
            $groups = array();
            // iterate all attributes
            while ($attribute = $result->fetch()) {
                $group = $attribute['group_name'];
                $option = $attribute['option_name'];
                $price = $attribute['price'];


                // Create / load group
                if (!isset($groups[$group])) {
                    $groupId = Shopware()->Db()->fetchOne("
                        SELECT `id`
                        FROM `s_article_configurator_groups`
                        WHERE `name`='{$group}' LIMIT 1
                    ");
                    if ($groupId === false) {
                        $sql = "INSERT INTO `s_article_configurator_groups` SET `name`='{$group}'";
                        Shopware()->Db()->query($sql);
                        $groupId = Shopware()->Db()->lastInsertId();
                    }
                    $groups[$group] = $groupId;
                } else {
                    $groupId = $groups[$group];
                }

                // Set group relations
                $sql = "INSERT IGNORE INTO s_article_configurator_set_group_relations
                    (`set_id`, `group_id`)
                    VALUES ({$configuratorSetId}, {$groupId})
                ";
                Shopware()->Db()->query($sql);

                // Create / load option
                if (!isset($options[$option])) {
                    $optionId = Shopware()->Db()->fetchOne("
                        SELECT `id`
                        FROM `s_article_configurator_options`
                        WHERE `name`='{$option}' AND `group_id`={$groupId}
                    ");
                    if ($optionId === false) {
                        $sql = "INSERT INTO `s_article_configurator_options` (`group_id`, `name`) VALUES ({$groupId}, '{$option}')";
                        Shopware()->Db()->query($sql);
                        $optionId = Shopware()->Db()->lastInsertId();
                    }
                    $options[$option] = $optionId;
                } else {
                    $optionId = $options[$option];
                }

                // Set option relations
                $sql = "INSERT IGNORE INTO s_article_configurator_set_option_relations (`set_id`, `option_id`) VALUES ({$configuratorSetId}, {$optionId})";
                Shopware()->Db()->query($sql);

                if ($price) {
                    $sql = "INSERT INTO `s_article_configurator_price_surcharges` (`configurator_set_id`, `parent_id`, `surcharge`) VALUES ({$configuratorSetId}, {$optionId}, {$price})";
                    Shopware()->Db()->query($sql);
                }

            }

            // Set product's configurator set
            $sql = "UPDATE s_articles SET configurator_set_id = {$configuratorSetId} WHERE `id`={$productId}";
            Shopware()->Db()->query($sql);


            $offset++;
            if(time()-$requestTime >= 10) {
                echo Zend_Json::encode(array(
                    'message'=>sprintf($this->namespace->get('configuratorProgress', "%s out of %s configurators imported"), $offset, $count),
                    'success'=>true,
                    'offset'=>$offset,
                    'progress'=>$offset/$count,
	                'estimated' => (time()-$taskStartTime)/$offset * ($count-$offset),
                    'task_start_time' => $taskStartTime
                ));
                return;
            }

        }
        echo Zend_Json::encode(array(
            'message'=>$this->namespace->get('generatedConfigurators', "Configurators successfully created!"),
            'success'=>true,
            'import_generate_variants'=>null,
            // Set variant generation to be the next step
            'import_create_configurator_variants' => 1,
            'offset'=>0,
            'progress'=>-1
        ));

    }

    /**
     * Generate variants from configuratorSets
     */
    public function generateVariants()
    {
        $requestTime = !empty($_SERVER['REQUEST_TIME']) ? $_SERVER['REQUEST_TIME'] : time();
        $offsetProduct = empty($this->Request()->offset) ? 0 : (int) $this->Request()->offset;

        $done = Zend_Json::encode(array(
            'message'=>$this->namespace->get('generatedVariants', "Variants successfully generated"),
            'success'=>true,
            'count' => 0,
            'import_generate_variants'=>null,
            'import_create_configurator_variants' => null,
            'offset'=>0,
            'progress'=>-1
        ));

        // Get products with attributes
        $products_result = $this->Source()->queryAttributedProducts($offsetProduct);
        if (empty($products_result)) {
            echo $done;
            return;
        }

        $count = $products_result->rowCount()+$offsetProduct;

	    $taskStartTime  = $this->initTaskTimer();

        // iter products
        while ($product = $products_result->fetch()) {
            $id = $product['productID'];

            // continue if product was not imported before
            $productId = $this->getBaseArticleInfo($id);
            if (false === $productId) {
                continue;
            }

            $groups = $this->Helpers()->getConfiguratorGroups($productId);

            $params = array(
                'articleId' => $productId,
                'groups' => $groups

            );

            $offsetProduct++;

            // Return the groups
            // The ExtJS frontend will care of the generation by triggering
            // the default article controller
            echo Zend_Json::encode(array(
                'message'=>sprintf($this->namespace->get('variantsArticleProgress', "Generating variants for product %s out of %s"), $offsetProduct, $count),
                'success'=>true,
                'offset'=>$offsetProduct,
                'count'=>$count,
                'create_variants' => true,
                'params' => $params,
                'progress'=>$offsetProduct/$count,
	            'estimated' => (time()-$taskStartTime)/$offsetProduct * ($count-$offsetProduct),
                'task_start_time' => $taskStartTime
            ));
            return;
        }

        echo $done;
    }


	public function importProductProperties()
	{
		$requestTime = !empty($_SERVER['REQUEST_TIME']) ? $_SERVER['REQUEST_TIME'] : time();
        $offset = empty($this->Request()->offset) ? 0 : (int) $this->Request()->offset;

		if ($this->printCurrentImportMessage('Product Properties')) {
		    return;
		}

		$done = Zend_Json::encode(array(
			'message'=>$this->namespace->get('importProductProperties', "Properties imported"),
			'success'=>true,
			'count' => 0,
			'import_properties'=>null,
			'offset'=>0,
			'progress'=>-1
		));

		// Get products with attributes
		$result = $this->Source()->queryProductProperties($offset);
		if (empty($result)) {
			echo $done;
			return;
		}

        $count = $result->rowCount()+$offset;
		$count = $this->Source()->getEstimation('properties');
		error_log($count);
		$taskStartTime  = $this->initTaskTimer();

        while ($property = $result->fetch()) {
            // Skip products which have not been imported before
            $productId = $this->getBaseArticleInfo($property['productID']);
            if (false === $productId) {
                continue;
            }
	        $data = array(
		        'productID' => $productId,
		        'group' => array(
			        'name' => $property['group'],
					'options' => array(
						array(
							'name' => $property['option'],
							'values' => array(
								array('value' => $property['value'])
							)
						)
					)
		        )
	        );

			$this->Helpers()->importProductProperty($data);

		    $offset++;
	        if(time()-$requestTime >= 10) {
	            echo Zend_Json::encode(array(
	                'message'=>sprintf($this->namespace->get('progressProductProperties', "%s out of %s product properties imported"), $offset, $count),
	                'success'=>true,
	                'offset'=>$offset,
	                'progress'=>$offset/$count,
	             'estimated' => (time()-$taskStartTime)/$offset * ($count-$offset),
	                'task_start_time' => $taskStartTime
	            ));
	            return;
	        }
		}

		  echo Zend_Json::encode(array(
		      'message'=>sprintf($this->namespace->get('progressProductProperties', "%s out of %s product properties imported"), $offset, $count),
		      'success'=>true,
		      'offset'=>$offset,
		      'progress'=>$offset/$count,
		   'estimated' => (time()-$taskStartTime)/$offset * ($count-$offset),
		      'task_start_time' => $taskStartTime
		  ));
      return;


//		echo $done;

	}

    /**
     * This function imports the customers, selected by the source profile.
     * @return mixed
     */
    public function importCustomers()
    {
        $requestTime = !empty($_SERVER['REQUEST_TIME']) ? $_SERVER['REQUEST_TIME'] : time();
        $offset = empty($this->Request()->offset) ? 0 : (int) $this->Request()->offset;

        if ($this->printCurrentImportMessage('Customers')) {
            return;
        }

        $result = $this->Source()->queryCustomers($offset);
        $count = $result->rowCount()+$offset;

	    $taskStartTime  = $this->initTaskTimer();

        while ($customer = $result->fetch()) {

            if(isset($customer['customergroupID']) && isset($this->Request()->customer_group[$customer['customergroupID']])) {
                $customer['customergroup'] = $this->Request()->customer_group[$customer['customergroupID']];
            }
            unset($customer['customergroupID']);
            if(isset($customer['subshopID']) && isset($this->Request()->shop[$customer['subshopID']])) {
                $customer['subshopID'] = $this->Request()->shop[$customer['subshopID']];
            } else {
                unset($customer['subshopID']);
            }
            if(!empty($customer['billing_countryiso'])) {
                $sql = 'SELECT `id` FROM `s_core_countries` WHERE `countryiso` = ?';
                $customer['billing_countryID'] = (int) Shopware()->Db()->fetchOne($sql , array($customer['billing_countryiso']));
            }
            if(isset($customer['shipping_countryiso'])) {
                $sql = 'SELECT `id` FROM `s_core_countries` WHERE `countryiso` = ?';
                $customer['shipping_countryID'] = (int) Shopware()->Db()->fetchOne($sql , array($customer['shipping_countryiso']));
            }

            if(!isset($customer['paymentID'])) {
                $customer['paymentID'] = Shopware()->Config()->Paymentdefault;
            }


            if(!empty($customer['shipping_company'])||!empty($customer['shipping_firstname'])||!empty($customer['shipping_lastname'])) {
                $customer_shipping = array(
                    'company' => !empty($customer['shipping_company']) ? $customer['shipping_company'] : '',
                    'department' => !empty($customer['shipping_department']) ? $customer['shipping_department'] : '',
                    'salutation' => !empty($customer['shipping_salutation']) ? $customer['shipping_salutation'] : '',
                    'firstname' => !empty($customer['shipping_firstname']) ? $customer['shipping_firstname'] : '',
                    'lastname' => !empty($customer['shipping_lastname']) ? $customer['shipping_lastname'] : '',
                    'street' => !empty($customer['shipping_street']) ? $customer['shipping_street'] : '',
                    'streetnumber' => !empty($customer['shipping_streetnumber']) ? $customer['shipping_streetnumber'] : '',
                    'zipcode' => !empty($customer['shipping_zipcode']) ? $customer['shipping_zipcode'] : '',
                    'city' => !empty($customer['shipping_city']) ? $customer['shipping_city'] : '',
                    'countryID' => !empty($customer['shipping_countryID']) ? $customer['shipping_countryID'] : 0,
                );
                $customer['shipping_company'] = $customer['shipping_firstname'] = $customer['shipping_lastname'] = '';
            } else {
                $customer_shipping = array();
            }

            $customer_result = Shopware()->Api()->Import()->sCustomer($customer);

            if(!empty($customer_result)) {
                $customer = array_merge($customer, $customer_result);

                if(!empty($customer_shipping)) {
                    $customer_shipping['userID'] = $customer['userID'];
                    Shopware()->Db()->insert('s_user_shippingaddress', $customer_shipping);
                }

                $sql = '
                    INSERT INTO `s_plugin_migrations` (`typeID`, `sourceID`, `targetID`)
                    VALUES (?, ?, ?)
                    ON DUPLICATE KEY UPDATE `targetID`=VALUES(`targetID`);
                ';
                Shopware()->Db()->query($sql , array(Shopware_Components_Migration_Helpers::MAPPING_CUSTOMER, $customer['customerID'], $customer['userID']));
            }
            $offset++;
            if(time()-$requestTime >= 10) {
                echo Zend_Json::encode(array(
                    'message'=>sprintf($this->namespace->get('progressCustomers', "%s out of %s customers imported"), $offset, $count),
                    'success'=>true,
                    'offset'=>$offset,
                    'progress'=>$offset/$count,
	                'estimated' => (time()-$taskStartTime)/$offset * ($count-$offset),
                    'task_start_time' => $taskStartTime
                ));
                return;
            }
        }

        echo Zend_Json::encode(array(
            'message'=>$this->namespace->get('importedCustomers', "Customers successfully imported!"),
            'success'=>true,
            'import_customers'=>null,
            'offset'=>0,
            'progress'=>-1
        ));
    }

    /**
     * This function import all orders from the source profile database into the shopware database.
     * @return mixed
     */
    public function importOrders()
    {
        $requestTime = !empty($_SERVER['REQUEST_TIME']) ? $_SERVER['REQUEST_TIME'] : time();
        $offset = empty($this->Request()->offset) ? 0 : (int) $this->Request()->offset;

        if ($this->printCurrentImportMessage('Orders')) {
            return;
        }

        $result = $this->Source()->queryOrders($offset);
        $count = $result->rowCount()+$offset;

	    $taskStartTime  = $this->initTaskTimer();

        while ($order = $result->fetch()) {


            if(isset($order['languageID']) && isset($this->Request()->language[$order['languageID']])) {
                $order['languageID'] = $this->Request()->language[$order['languageID']];
            }
            if(isset($order['subshopID']) && isset($this->Request()->shop[$order['subshopID']])) {
                $order['subshopID'] = $this->Request()->shop[$order['subshopID']];
            }
            if(isset($order['statusID']) && isset($this->Request()->order_status[$order['statusID']])) {
                $order['statusID'] = $this->Request()->order_status[$order['statusID']];
            }
            if(isset($order['paymentID']) && isset($this->Request()->payment_mean[$order['paymentID']])) {
                $order['paymentID'] = $this->Request()->payment_mean[$order['paymentID']];
            }else {
                $order['paymentID'] = Shopware()->Config()->Paymentdefault;
            }

            $sql = 'SELECT `targetID` FROM `s_plugin_migrations` WHERE `typeID`=? AND `sourceID`=?';
            $order['userID'] = Shopware()->Db()->fetchOne($sql , array(Shopware_Components_Migration_Helpers::MAPPING_CUSTOMER, $order['customerID']));

            $order['sourceID'] = $order['orderID'];
            $sql = 'SELECT `targetID` FROM `s_plugin_migrations` WHERE `typeID`=? AND `sourceID`=?';
            $order['orderID'] = Shopware()->Db()->fetchOne($sql , array(Shopware_Components_Migration_Helpers::MAPPING_ORDER, $order['orderID']));

            $data = array(
                'ordernumber' => $order['ordernumber'],
                'invoice_amount' => !empty($order['invoice_amount']) ? $order['invoice_amount'] : 0,
                'invoice_amount_net' => !empty($order['invoice_amount_net']) ? $order['invoice_amount_net'] : 0,
                'userID' => $order['userID'],
                'invoice_shipping' => !empty($order['invoice_shipping']) ? $order['invoice_shipping'] : 0,
                'invoice_shipping_net' => !empty($order['invoice_shipping_net']) ? $order['invoice_shipping_net'] : 0,
                'ordertime' => isset($order['date']) ? $order['date'] : new Zend_Db_Expr('NOW()'),
                'status' => !empty($order['statusID']) ? (int) $order['statusID'] : 0,
                'cleared' => !empty($order['clearedID']) ? (int) $order['clearedID'] : 17,
                'paymentID' => (int) $order['paymentID'],
                'transactionID' => isset($order['transactionID']) ? $order['transactionID'] : '',
                'customercomment' => isset($order['customercomment']) ? $order['customercomment'] : '',
                'net' => !empty($order['tax_free'])||!empty($order['net']) ? 1 : 0,
                'taxfree' => !empty($order['tax_free']) ? 1 : 0,
                'referer' => isset($order['referer']) ? $order['referer'] : '',
                'cleareddate' => isset($order['cleared_date']) ? $order['cleared_date'] : NULL,
                'trackingcode' => isset($order['trackingID']) ? $order['trackingID'] : '',
                'language' => !empty($order['languageID']) ? $order['languageID'] : 'de',
                'dispatchID' => !empty($order['dispatchID']) ? (int) $order['dispatchID'] : 0,
                'currency' => !empty($order['currency']) ? $order['currency'] : 'EUR',
                'currencyFactor' => !empty($order['currency_factor']) ? $order['currency_factor'] : 1,
                'subshopID' => isset($order['subshopID']) ? $order['subshopID'] : 0,
                'remote_addr' => isset($order['remote_addr']) ? $order['remote_addr'] : '',
            );

            if($data['cleareddate'] === '0000-00-00 00:00:00') {
                $data['cleareddate'] = NULL;
            }

            if(!empty($order['orderID'])) {
                Shopware()->Db()->update('s_order', $data, array('id=?'=>$order['orderID']));
                $sql = 'DELETE FROM `s_order_details` WHERE `orderID`=?';
                Shopware()->Db()->query($sql , array($order['orderID']));
            } else {
                $order['insert'] = Shopware()->Db()->insert('s_order', $data);
                $order['orderID'] = Shopware()->Db()->lastInsertId();

	            $sql = 'INSERT INTO `s_plugin_migrations` (`typeID`, `sourceID`, `targetID`)
	            VALUES (?, ?, ?)
	            ON DUPLICATE KEY UPDATE `targetID`=VALUES(`targetID`);
	            ';
	            Shopware()->Db()->query($sql, array(Shopware_Components_Migration_Helpers::MAPPING_ORDER, $order['sourceID'], $order['orderID']));
            }

            if(!empty($order['billing_countryiso'])) {
                $sql = 'SELECT `id` FROM `s_core_countries` WHERE `countryiso` = ?';
                $order['billing_countryID'] = (int) Shopware()->Db()->fetchOne($sql , array($order['billing_countryiso']));
            }
            if(isset($order['shipping_countryiso'])) {
                $sql = 'SELECT `id` FROM `s_core_countries` WHERE `countryiso` = ?';
                $order['shipping_countryID'] = (int) Shopware()->Db()->fetchOne($sql , array($order['shipping_countryiso']));
            }

            $data_billing = array(
                'userID' => $order['userID'],
                'orderID' => $order['orderID'],
                'company' => !empty($order['billing_company']) ? $order['billing_company'] : '',
                'department' => !empty($order['billing_department']) ? $order['billing_department'] : '',
                'salutation' => !empty($order['billing_salutation']) ? $order['billing_salutation'] : '',
                'customernumber' => !empty($order['billing_customernumber']) ? $order['billing_customernumber'] : '',
                'firstname' => !empty($order['billing_firstname']) ? $order['billing_firstname'] : '',
                'lastname' => !empty($order['billing_lastname']) ? $order['billing_lastname'] : '',
                'street' => !empty($order['billing_street']) ? $order['billing_street'] : '',
                'streetnumber' => !empty($order['billing_streetnumber']) ? $order['billing_streetnumber'] : '',
                'zipcode' => !empty($order['billing_zipcode']) ? $order['billing_zipcode'] : '',
                'city' => !empty($order['billing_city']) ? $order['billing_city'] : '',
                'phone' => !empty($order['phone']) ? $order['phone'] : '',
                'fax' => !empty($order['fax']) ? $order['fax'] : '',
                'countryID' => !empty($order['billing_countryID']) ? $order['billing_countryID'] : 0,
                'ustid' => !empty($order['billing_ustid']) ? $order['billing_ustid'] : '',
            );

            $data_shipping = array(
                'orderID' => $order['orderID'],
                'userID' => $order['userID'],
                'company' => !empty($order['shipping_lastname']) ?  $order['shipping_company'] : $data_billing['company'],
                'department' => !empty($order['shipping_lastname'])&&!empty($order['shipping_department']) ? $order['shipping_department'] : $data_billing['department'],
                'salutation' => !empty($order['shipping_lastname'])&&!empty($order['shipping_salutation']) ? $order['shipping_salutation'] : $data_billing['salutation'],
                'firstname' => !empty($order['shipping_lastname']) ? $order['shipping_firstname'] : $data_billing['firstname'],
                'lastname' => !empty($order['shipping_lastname']) ? $order['shipping_lastname'] : $data_billing['lastname'],
                'street' => !empty($order['shipping_lastname']) ? $order['shipping_street'] : $data_billing['street'],
                'streetnumber' => !empty($order['shipping_lastname'])&&!empty($order['shipping_streetnumber']) ? $order['shipping_streetnumber'] : $data_billing['streetnumber'],
                'zipcode' => !empty($order['shipping_lastname']) ? $order['shipping_zipcode'] : $data_billing['zipcode'],
                'city' => !empty($order['shipping_lastname']) ? $order['shipping_city'] : $data_billing['city'],
                'countryID' => !empty($order['shipping_lastname'])&&!empty($order['shipping_countryID']) ? $order['shipping_countryID'] : $data_billing['countryID'],
            );

            foreach($data_billing as $key => $attribute) {
                if($attribute === null) {
                    $data_billing[$key] = '';
                }
            }
            foreach($data_shipping as $key => $attribute) {
                if($attribute === null) {
                    $data_shipping[$key] = '';
                }
            }

            if(empty($order['insert'])) {
                Shopware()->Db()->update('s_order_billingaddress', $data_billing, array('orderID=?'=>$order['orderID']));
                Shopware()->Db()->update('s_order_shippingaddress', $data_shipping, array('orderID=?'=>$order['orderID']));
            } else {
                Shopware()->Db()->insert('s_order_billingaddress', $data_billing);
                Shopware()->Db()->insert('s_order_shippingaddress', $data_shipping);
            }

            $offset++;
            if(time()-$requestTime >= 10) {
                echo Zend_Json::encode(array(
                    'message'=>sprintf($this->namespace->get('progressOrders', "%s out of %s orders imported"), $offset, $count),
                    'success'=>true,
                    'offset'=>$offset,
                    'progress'=>$offset/$count,
	                'estimated' => (time()-$taskStartTime)/$offset * ($count-$offset),
                    'task_start_time' => $taskStartTime
                ));
                return;
            }
        }

        echo Zend_Json::encode(array(
            'message'=>$this->namespace->get('importedOrders', "Orders successfully imported!"),
            'success'=>true,
            'import_orders'=>null,
            'import_order_details'=>1,
            'offset'=>0,
            'progress'=>-1
        ));
    }

    /**
     * This function imports all order details from the source profile into the showpare database
     * @return mixed
     */
    public function importOrderDetails()
    {
        $requestTime = !empty($_SERVER['REQUEST_TIME']) ? $_SERVER['REQUEST_TIME'] : time();
        $offset = empty($this->Request()->offset) ? 0 : (int) $this->Request()->offset;
        $numberValidationMode = $this->Request()->getParam('number_validation_mode', 'complain');

        if ($this->printCurrentImportMessage('Order details')) {
            return;
        }

        $numberSnippet = $this->namespace->get('numberNotValid',
            "The product number %s is not valid. A valid product number must:<br>
            * not be longer than 40 chars<br>
            * not contain other chars than: 'a-zA-Z0-9-_.' and SPACE<br>
            <br>
            You can force the migration to continue. But be aware that this will: <br>
            * Truncate ordernumbers longer than 40 chars and therefore result in 'duplicate keys' exceptions <br>
            * Will not allow you to modify and save articles having an invalid ordernumber <br>
            ");

        $result = $this->Source()->queryOrderDetails($offset);
        $count = $result->rowCount()+$offset;

	    $taskStartTime  = $this->initTaskTimer();

        while ($order = $result->fetch()) {

            // Check the ordernumber
            $number = $order['article_ordernumber'];
            if ($numberValidationMode !== 'ignore' && isset($number) &&
                (strlen($number) > 40 || preg_match('/[^a-zA-Z0-9-_. ]/', $number)))
            {
                switch ($numberValidationMode) {
                    case 'complain':
                        echo Zend_Json::encode(array(
                            'message'=>sprintf($numberSnippet, $number),
                            'success'=>false,
                            'import_products'=>null,
                            'offset'=>0,
                            'progress'=>-1
                        ));
                        return;
                        break;
                    case 'make_valid':
                        $order['article_ordernumber'] = $this->makeInvalidNumberValid($number, $order['productID']);
                        break;
                }
            }


            $sql = 'SELECT `targetID` FROM `s_plugin_migrations` WHERE `typeID`=? AND `sourceID`=?';
            $order['orderID'] = Shopware()->Db()->fetchOne($sql , array(Shopware_Components_Migration_Helpers::MAPPING_ORDER, $order['orderID']));

            $sql = '
                SELECT ad.articleID
                FROM s_plugin_migrations pm
                JOIN s_articles_details ad
                ON ad.id=pm.targetID
                WHERE pm.`sourceID`=?
                AND `typeID`=?
            ';
            $order['articleID'] = $this->Target()->Db()->fetchOne($sql, array($order['productID'], Shopware_Components_Migration_Helpers::MAPPING_ARTICLE));

            //TaxRate
            if(!empty($this->Request()->tax_rate) && isset($order['taxID'])) {
                if(isset($this->Request()->tax_rate[$order['taxID']])) {
                    $order['taxID'] = $this->Request()->tax_rate[$order['taxID']];
                } else {
                    unset($order['taxID']);
                }
            }
            if(!empty($order['tax']) && empty($order['taxID'])) {
                $sql = 'SELECT `id` FROM `s_core_tax` WHERE `tax`=?';
                $order['taxID'] = Shopware()->Db()->fetchOne($sql , array($order['tax']));
            }

            if(!empty($order['articleID']) && empty($order['taxID'])) {
                $sql = 'SELECT `taxID` FROM `s_articles` WHERE `id`=?';
                $order['taxID'] = Shopware()->Db()->fetchOne($sql , array($order['articleID']));
            }

            $data = array(
                'orderID' => $order['orderID'],
                'articleID' => isset($order['articleID']) ? (int) $order['articleID'] : 0,
                'articleordernumber' => $order['article_ordernumber'],
                'ordernumber' => !empty($order['ordernumber']) ? $order['ordernumber'] : '',
                'name' => $order['name'],
                'price' => $order['price'],
                'taxID' =>  !empty($order['taxID']) ? $order['taxID'] : 0,
                'quantity' =>  !empty($order['quantity']) ? $order['quantity'] : 1,
                'modus' =>  !empty($order['modus']) ? $order['modus'] : 0
            );

            foreach($data as $key => $attribute) {
                if($attribute === null) {
                    $data[$key] = '';
                }
            }

            Shopware()->Db()->insert('s_order_details', $data);

            $offset++;
            if(time()-$requestTime >= 10) {
                echo Zend_Json::encode(array(
                    'message'=>sprintf($this->namespace->get('progressOrderDetails', "%s out of %s order details imported"), $offset, $count),
                    'success'=>true,
                    'offset'=>$offset,
                    'progress'=>$offset/$count,
	                'estimated' => (time()-$taskStartTime)/$offset * ($count-$offset),
                    'task_start_time' => $taskStartTime
                ));
                return;
            }
        }

        echo Zend_Json::encode(array(
            'message'=>$this->namespace->get('importedOrders', "Orders successfully imported!"),
            'success'=>true,
            'import_order_details'=>null,
            'offset'=>0,
            'progress'=>-1
        ));
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
     * This function imports the different data types.
     * @return mixed|void
     */
    public function importAction()
    {
        $this->namespace = Shopware()->Snippets()->getNamespace('backend/swag_migration/main');
        $this->Front()->Plugins()->Json()->setRenderer(false);

        try {
            $errorMessage = '';

            if(!empty($this->Request()->import_products)) {
                $errorMessage = $this->namespace->get('errorImportingProducts', "An error occurred while importing products");
                return $this->importProducts();
            }

            if(!empty($this->Request()->import_translations)) {
                $errorMessage = $this->namespace->get('errorImportingTranslations', "An error occurred while importing translations");
				return $this->importProductTranslations();
            }

	        if(!empty($this->Request()->import_properties)) {
                $errorMessage = $this->namespace->get('errorImportingProductProperties', "An error occurred while importing product properties");
				return $this->importProductProperties();
            }

            if(!empty($this->Request()->import_categories)) {
                $errorMessage = $this->namespace->get('errorImportingCategories', "An error occurred while importing categories");
                return $this->importCategories();
            }

            if(!empty($this->Request()->import_article_categories)) {
                $errorMessage = $this->namespace->get('errorImportingArticleCategories', "An error assigning articles to categories");
                return $this->importArticleCategories();
            }

            if(!empty($this->Request()->import_prices)) {
                $errorMessage = $this->namespace->get('errorImportingPrices', "An error occurred while importing prices");
                return $this->importProductPrices();
            }

            if(!empty($this->Request()->import_generate_variants)) {
                $errorMessage = $this->namespace->get('errorGeneratingVariantsFromAttributes', "An error occurred while generating configuratos");
                return $this->generateConfiguratorSetsFromAttributes();
            }

            if(!empty($this->Request()->import_create_configurator_variants)) {
                $errorMessage = $this->namespace->get('errorGeneratingVariantsFromAttributes', "An error occurred while generating configurator variants");
                return $this->generateVariants();
            }

            if(!empty($this->Request()->import_images)) {
                $errorMessage = $this->namespace->get('errorImportingImages', "An error occurred while importing images");
                return $this->importProductImages();
            }

            if(!empty($this->Request()->import_customers)) {
                $errorMessage = $this->namespace->get('errorImportingCustomers', "An error occurred while importing customers");
                return $this->importCustomers();
            }

            if(!empty($this->Request()->import_ratings)) {
                $errorMessage = $this->namespace->get('errorImportingRatings', "An error occurred while importing ratings");
                return $this->importProductRatings();
            }

            if(!empty($this->Request()->import_orders)) {
                $errorMessage = $this->namespace->get('errorImportingOrders', "An error occurred while importing orders");
                return $this->importOrders();
            }

            if(!empty($this->Request()->import_order_details)) {
                $errorMessage = $this->namespace->get('errorImportingOrderDetails', "An error occurred while importing order details");
                return $this->importOrderDetails();
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

        } catch (Exception $e) {
            $error = array(
                'message'=>$errorMessage,
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