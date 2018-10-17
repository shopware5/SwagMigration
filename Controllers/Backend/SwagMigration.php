<?php
/**
 * (c) shopware AG <info@shopware.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
use Shopware\SwagMigration\Components\Migration;
use Shopware\SwagMigration\Components\Migration\Cleanup;
use Shopware\SwagMigration\Components\Migration\Import\Progress;
use Shopware\SwagMigration\Components\Migration\Import\Resource\AbstractResource;
use Shopware\SwagMigration\Components\Migration\Mapping;
use Shopware\SwagMigration\Components\Migration\Profile;

/**
 * (c) shopware AG <info@shopware.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
class Shopware_Controllers_Backend_SwagMigration extends Shopware_Controllers_Backend_ExtJs
{
    /**
     * Defines all availabe imports as well as the order of their import
     *
     * @var array
     */
    public $imports = [
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
        'import_order_details' => 'Order',
        'import_downloads' => 'Download',
        'import_downloads_esd' => 'DownloadESD',
        'import_orders_esd' => 'DownloadESDOrder',
    ];

    /**
     * Source shop system profile
     *
     * @var Profile
     */
    protected $source;

    /**
     * Target shop system profile
     *
     * @var Profile
     */
    protected $target;

    /**
     * Mapping helper
     *
     * @var Mapping
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

        $this->View()->addTemplateDir(__DIR__ . '/../../Views/');
        parent::init();
    }

    /**
     * This function inits the source profile and creates it over the profile factory
     *
     * @return Enlight_Class
     */
    public function initSource()
    {
        $config = Shopware()->Container()->getParameter('shopware.db');

        // Setting the current shopware database as default will fail,
        // if the user wants to connect to a remote database. So the dbname
        // needs to be unset
        $config['dbname'] = '';

        // Populate the config object by the request data
        $query = $this->Request()->getPost() + $this->Request()->getQuery();
        if (isset($query['username']) && $query['username'] !== 'default') {
            $config['username'] = $query['username'];
        }
        if (isset($query['prefix']) && $query['prefix'] !== 'default') {
            $config['prefix'] = $query['prefix'];
        }
        if (isset($query['password']) && $query['password'] !== 'default') {
            $config['password'] = $query['password'];
        }
        if (isset($query['host']) && $query['host'] !== 'default') {
            $config['host'] = $query['host'];
        }
        if (isset($query['port']) && $query['port'] !== 'default') {
            $config['port'] = $query['port'];
        }
        if (isset($query['database']) && $query['database'] !== 'default') {
            $config['dbname'] = $query['database'];
        }

        return Migration::profileFactory($query['profile'], $config);
    }

    /**
     * Returns an instance of the migration mapping helper
     *
     * @return Mapping
     */
    public function Mapping()
    {
        if (!isset($this->mapping)) {
            $this->mapping = new Mapping(
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
     * @return Profile
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
        $config = (array) Shopware()->Container()->getParameter('shopware.db');

        return Migration::profileFactory('Shopware', $config);
    }

    /**
     * Getter method of the target profile. If the profile is not set, the controller initial the profile first.
     *
     * @return Profile
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

        $cleanup = new Cleanup();
        $cleanup->cleanUpByArray(array_keys($data));

        echo Zend_Json::encode(['success' => true]);
    }

    /**
     * Returns the possible migration profiles
     */
    public function profileListAction()
    {
        $this->setRenderer(false);

        $rows = [
            ['id' => 'Magento2', 'name' => $this->getNamespace()->get('profile-magento-2')],
            ['id' => 'Magento', 'name' => $this->getNamespace()->get('profile-magento')],
            ['id' => 'Magento17', 'name' => $this->getNamespace()->get('profile-magento-old')],
            ['id' => 'Oxid', 'name' => $this->getNamespace()->get('profile-oxid')],
            ['id' => 'Veyton', 'name' => $this->getNamespace()->get('profile-xt4')],
            ['id' => 'Gambio', 'name' => $this->getNamespace()->get('profile-gambio')],
            ['id' => 'Xt Commerce', 'name' => $this->getNamespace()->get('profile-xt')],
            ['id' => 'Prestashop15', 'name' => $this->getNamespace()->get('profile-presta')],
            ['id' => 'Prestashop14', 'name' => $this->getNamespace()->get('profile-presta-old')],
            ['id' => 'WooCommerce', 'name' => $this->getNamespace()->get('profile-woo')],
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
            $msg = sprintf('An error occured: %s', $e->getMessage());
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

        $rows = $this->Mapping()->getMappingForEntity($this->Request()->getParam('mapping'));

        echo Zend_Json::encode(['data' => $rows, 'count' => count($rows)]);
    }

    /**
     * This function validate the first form panel
     */
    public function checkFormAction()
    {
        $call = array_merge($this->Request()->getPost(), $this->Request()->getQuery());
        $this->setRenderer(false);

        try {
            if ($call['profile'] === 'WooCommerce') {
                $shops = $this->Source()->getNormalizedShops();
                $languages = $this->Source()->getNormalizedLanguages();
            } else {
                $shops = $this->Source()->getShops();
                $languages = $this->Source()->getLanguages();
                //$image_path = rtrim($this->Request()->basepath.$this->Source()->getProductImagePath(), '/').'/';
                //$client = new Zend_Http_Client($image_path);
                echo Zend_Json::encode(['success' => true]);
            }
        } catch (Zend_Db_Statement_Exception $e) {
            switch ($e->getCode()) {
                case 42:
                    echo Zend_Json::encode(
                        [
                            'success' => false,
                            'message' => $this->getNamespace()->get(
                                'databaseProfileDoesNotMatch',
                                'The selected profile does not match the selected database. Please make sure that the selected database is the database you want to import.'
                            ),
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
        $cleanup = new Cleanup();
        $cleanup->clearMigrationMappings();

        echo Zend_Json::encode(
            [
                'message' => $this->getNamespace()->get('importFinished', 'Import finished'),
                'success' => true,
                'progress' => 1,
                'done' => true,
            ]
        );
    }

    /**
     * Creates an instance of the import resource needed to import $importType
     *
     * Will also inject the dependencies needed and return the created object
     *
     * @param $importType string The import resource to create
     *
     * @return AbstractResource
     */
    public function initImport($importType)
    {
        $offset = (int) $this->Request()->getParam('offset', 0);
        $name = $this->imports[$importType];

        /** @var $progress Progress */
        $progress = new Progress();
        $progress->setOffset($offset);

        $import = Migration::resourceFactory(
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

        if (!empty($this->Request()->getParam('finish_import'))) {
            $this->finishImport();

            return;
        }

        echo Zend_Json::encode(
            [
                'message' => $this->getNamespace()->get('importedSelectedData', 'Selected data successfully imported!'),
                'success' => true,
                'progress' => 1,
                'done' => true,
            ]
        );
    }

    /**
     * Helper method to print "currently importing" messages
     *
     * @param $type
     *
     * @return bool
     */
    public function printCurrentImportMessage($type)
    {
        $offset = (int) $this->Request()->getParam('offset', 0);
        if ($offset > 0) {
            return false;
        }
        $messageShown = (bool) $this->Request()->getParam('messageShown', false);
        if ($messageShown) {
            return false;
        }
        $import = $this->getNamespace()->get('currentImport' . $type, $type);

        echo Zend_Json::encode(
            [
                'message' => sprintf(
                    $this->getNamespace()->get('currentlyImporting', 'Current import step: %s'),
                    $import
                ),
                'success' => true,
                'offset' => 0,
                'progress' => 0,
                'messageShown' => 1,
            ]
        );

        return true;
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
            'done' => true,
        ];
        if (!$this->Front()->Plugins()->Json()->getRenderer()) {
            echo Zend_Json::encode($error);
        } else {
            $this->view->assign($error);
        }
    }
}
