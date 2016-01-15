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
     * Defines all availabe imports as well as the order of their import
     *
     * @var array
     */
    public $imports = [
        'import_products'                     => 'Product',
        'import_translations'                 => 'Translation',
        'import_properties'                   => 'Property',
        'import_categories'                   => 'Category',
        'import_article_categories'           => 'Category',
        'import_prices'                       => 'Price',
        'import_generate_variants'            => 'Configurator',
        'import_create_configurator_variants' => 'Variant',
        'import_images'                       => 'Image',
        'import_customers'                    => 'Customer',
        'import_ratings'                      => 'Rating',
        'import_orders'                       => 'Order',
        'import_order_details'                => 'Order',
        'import_downloads'                    => 'Download',
        'import_downloads_esd'                => 'DownloadESD',
        'import_orders_esd'                   => 'DownloadESDOrder'
    ];

    /**
     * Source shop system profile
     *
     * @var Shopware_Components_Migration_Profile
     */
    protected $source;

    /**
     * Target shop system profile
     *
     * @var Shopware_Components_Migration_Profile
     */
    protected $target;

    /**
     * Mapping helper
     *
     * @var Shopware_Components_Migration_Mapping
     */
    protected $mapping;

    /**
     * Snippet namespace
     *
     * @var Enlight_Components_Snippet_Namespace
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
     * Returns the snippet namespace object for the migration namespace
     *
     * @return Enlight_Components_Snippet_Namespace
     */
    public function getNamespace()
    {
        if (!isset($this->namespace)) {
            $this->namespace = Shopware()->Snippets()->getNamespace('backend/swag_migration/main');
        }

        return $this->namespace;
    }

    /**
     * Set the renderer - most often used, to disable the renderer
     *
     * @param $renderer
     */
    public function setRenderer($renderer)
    {
        /** @var Enlight_Controller_Plugins_Json_Bootstrap $json */
        $json = $this->Front()->Plugins()->Json();
        $json->setRenderer($renderer);
    }

    /**
     * This function add the template directory and register the Shopware_Components namespace
     */
    public function init()
    {
        $this->setMaxExecutionTime();

        Shopware()->Loader()->registerNamespace('Shopware_Components', dirname(__FILE__) . '/../../Components/');
        $this->View()->addTemplateDir(dirname(__FILE__) . "/../../Views/");
        parent::init();
    }

    /**
     * This function inits the source profile and creates it over the profile factory
     *
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
        $query = $this->Request()->getPost() + $this->Request()->getQuery();
        if (isset($query['username']) && $query['username'] != 'default') {
            $config['username'] = $query['username'];
        }
        if (isset($query['prefix']) && $query['prefix'] != 'default') {
            $config['prefix'] = $query['prefix'];
        }
        if (isset($query['password']) && $query['password'] != 'default') {
            $config['password'] = $query['password'];
        }
        if (isset($query['host']) && $query['host'] != 'default') {
            $config['host'] = $query['host'];
        }
        if (isset($query['port']) && $query['port'] != 'default') {
            $config['port'] = $query['port'];
        }
        if (isset($query['database']) && $query['database'] != 'default') {
            $config['dbname'] = $query['database'];
        }

        return Shopware_Components_Migration::profileFactory($query['profile'], $config);
    }

    /**
     * Returns an instance of the migration mapping helper
     *
     * @return Shopware_Components_Migration_Mapping
     */
    public function Mapping()
    {
        if (!isset($this->mapping)) {
            $this->mapping = new Shopware_Components_Migration_Mapping(
                $this->Source(),
                $this->Target(),
                $this->getNamespace()
            );
        }

        return $this->mapping;
    }

    /**
     * Getter function of the source profile
     *
     * @return Shopware_Components_Migration_Profile
     */
    public function Source()
    {
        if (!isset($this->source)) {
            $this->source = $this->initSource();
        }

        return $this->source;
    }

    /**
     * Initial the target profile. The target profile type is every time shopware
     *
     * @return Enlight_Class
     */
    public function initTarget()
    {
        $config = (array) Shopware()->getOption('db');

        return Shopware_Components_Migration::profileFactory('Shopware', $config);
    }

    /**
     * Getter method of the target profile. If the profile is not set, the controller initial the profile first.
     *
     * @return Shopware_Components_Migration_Profile
     */
    public function Target()
    {
        if (!isset($this->target)) {
            $this->target = $this->initTarget();
        }

        return $this->target;
    }

    /**
     * Set the max_execution_timout. After the configured amount of seconds the controller will return
     * to the ExtJS controller in order to trigger a new request.
     *
     * The default value is 10 seconds, if a higher value is configured, this will used (reduced by 10).
     * However: No value higher than 60 seconds is possible - we need *some* responsiveness
     *
     * @return void
     */
    public function setMaxExecutionTime()
    {
        $value = 10;

        $configValue = (int) ini_get('max_execution_time');

        // We don't want infinite execution time - set it to 40 seconds in case
        if ($configValue <= 0) {
            $configValue = 40;
        }

        if ($configValue > 20) {
            $value = $configValue - 10;
        }

        // Don't allow values above a minute
        $value = min(60, $value);

        $this->max_execution = $value;
    }

    /**
     * This function is used to reset the shop. It will truncated all tables related to a given source
     */
    public function clearShopAction()
    {
        $this->setRenderer(false);
        $data = $this->Request()->getParams();

        $cleanup = new Shopware_Components_Migration_Cleanup();
        $cleanup->cleanUpByArray($data);

        echo Zend_Json::encode(['success' => true]);
    }

    /**
     * Returns the possible migration profiles
     */
    public function profileListAction()
    {
        $this->setRenderer(false);

        $rows = [
            ['id' => 'Magento', 'name' => $this->getNamespace()->get('profile-magento')],
            ['id' => 'Oxid', 'name' => $this->getNamespace()->get('profile-oxid')],
            ['id' => 'Veyton', 'name' => $this->getNamespace()->get('profile-xt4')],
            ['id' => 'Gambio', 'name' => $this->getNamespace()->get('profile-gambio')],
            ['id' => 'Xt Commerce', 'name' => $this->getNamespace()->get('profile-xt')],
            ['id' => 'Prestashop15', 'name' => $this->getNamespace()->get('profile-presta')],
            ['id' => 'Prestashop14', 'name' => $this->getNamespace()->get('profile-presta-old')],
        ];
        echo Zend_Json::encode(['data' => $rows, 'count' => count($rows)]);
    }

    /**
     * Returns the database list of the server.
     */
    public function databaseListAction()
    {
        $this->setRenderer(false);

        $rows = [];
        try {
            foreach ($this->Source()->getDatabases() as $database) {
                $rows[] = ['name' => $database];
            }
        } catch (\Exception $e) {
            $msg = sprintf("An error occured: %s", $e->getMessage());
            echo Zend_Json::encode(['success' => false, 'message' => $msg]);

            return;
        }

        echo Zend_Json::encode(['data' => $rows, 'count' => count($rows)]);
    }

    /**
     * This function returns the mapping list for the left grid
     */
    public function mappingListLeftAction()
    {
        $this->setRenderer(false);

        $rows = $this->Mapping()->getMappingLeft();

        echo Zend_Json::encode(['data' => $rows, 'count' => count($rows)]);
    }

    /**
     * This function returns the mapping list of the right grid panel
     */
    public function mappingListRightAction()
    {
        $this->setRenderer(false);

        $rows = $this->Mapping()->getMappingRight();

        echo Zend_Json::encode(['data' => $rows, 'count' => count($rows)]);
    }

    /**
     * This function returns the values for the grid combo boxes
     */
    public function mappingValuesListAction()
    {
        $this->setRenderer(false);

        $rows = $this->Mapping()->getMappingForEntity($this->Request()->mapping);

        echo Zend_Json::encode(['data' => $rows, 'count' => count($rows)]);
    }

    /**
     * This function validate the first form panel
     */
    public function checkFormAction()
    {
        $this->setRenderer(false);

        try {
            $shops = $this->Source()->getShops();
            $languages = $this->Source()->getLanguages();
            //$image_path = rtrim($this->Request()->basepath.$this->Source()->getProductImagePath(), '/').'/';
            //$client = new Zend_Http_Client($image_path);
            echo Zend_Json::encode(['success' => true]);
        } catch (Zend_Db_Statement_Exception $e) {
            switch ($e->getCode()) {
                case 42:
                    echo Zend_Json::encode(
                        [
                            'success' => false,
                            'message' => $this->getNamespace()->get(
                                'databaseProfileDoesNotMatch',
                                "The selected profile does not match the selected database. Please make sure that the selected database is the database you want to import."
                            )
                        ]
                    );
                    break;
                default:
                    echo Zend_Json::encode(['success' => false, 'message' => $e->getMessage()]);
            }
        } catch (Exception $e) {
            echo Zend_Json::encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    /**
     * This function finish the import and truncate the plugin table.
     */
    public function finishImport()
    {
        $cleanup = new Shopware_Components_Migration_Cleanup();
        $cleanup->clearMigrationMappings();

        echo Zend_Json::encode(
            [
                'message' => $this->getNamespace()->get('importFinished', "Import finished"),
                'success' => true,
                'progress' => 1,
                'done' => true
            ]
        );
    }

    /**
     * Convenience method which prints a given error for the extjs app
     *
     * @param $e \Exception
     * @param $errorDescription string A simple explanation of what happened
     */
    protected function printError($e, $errorDescription)
    {
        $error = [
            'message' => $errorDescription,
            'error' => $e->getMessage(),
            'code' => $e->getCode(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
            'success' => false,
            'progress' => 1,
            'done' => true
        ];
        if (!$this->Front()->Plugins()->Json()->getRenderer()) {
            echo Zend_Json::encode($error);
        } else {
            $this->view->assign($error);
        }
    }

    /**
     * Creates an instance of the import resource needed to import $importType
     *
     * Will also inject the dependencies needed and return the created object
     *
     * @param $importType string The import resource to create
     * @return Shopware_Components_Migration_Import_Resource_Abstract
     */
    public function initImport($importType)
    {
        $offset = empty($this->Request()->offset) ? 0 : (int) $this->Request()->offset;
        $name = $this->imports[$importType];

        /** @var $progress Shopware_Components_Migration_Import_Progress */
        $progress = new Shopware_Components_Migration_Import_Progress();
        $progress->setOffset($offset);

        $import = Shopware_Components_Migration::resourceFactory(
            $name,
            $progress,
            $this->Source(),
            $this->Target(),
            $this->request
        );
        $import->setInternalName($importType);
        $import->setMaxExecution($this->max_execution);

        return $import;
    }

    /**
     * Triggers the actual import for a given type
     *
     * @param $importType
     */
    public function runImport($importType)
    {
        $name = $this->imports[$importType];

        if ($this->printCurrentImportMessage($name)) {
            return;
        }

        $import = $this->initImport($importType);
        $progress = $import->getProgress();

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
     *
     * @return void
     */
    public function importAction()
    {
        $this->setRenderer(false);

        foreach ($this->imports as $key => $name) {
            if (!empty($this->Request()->$key)) {
                $this->runImport($key);

                return;
            }
        }

        if (!empty($this->Request()->finish_import)) {
            $this->finishImport();

            return;
        }

        echo Zend_Json::encode(
            [
                'message' => $this->getNamespace()->get('importedSelectedData', "Selected data successfully imported!"),
                'success' => true,
                'progress' => 1,
                'done' => true
            ]
        );
    }

    /**
     * Helper method to print "currently importing" messages
     *
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
        $import = $this->getNamespace()->get("currentImport" . $type, $type);

        echo Zend_Json::encode(
            [
                'message' => sprintf(
                    $this->getNamespace()->get('currentlyImporting', "Current import step: %s"),
                    $import
                ),
                'success' => true,
                'offset' => 0,
                'progress' => 0,
                'messageShown' => 1
            ]
        );

        return true;
    }
}
