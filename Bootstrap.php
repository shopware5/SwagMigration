<?php
/**
 * (c) shopware AG <info@shopware.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Doctrine\Common\Collections\ArrayCollection;
use Enlight_Components_Db_Adapter_Pdo_Mysql as PDOConnection;
use Shopware\SwagMigration\Commands\MigrateCommand;
use Shopware\SwagMigration\Components\Migration\PasswordEncoder\Md5Reversed;
use Shopware\SwagMigration\Subscriber\Resources;

/**
 * Shopware SwagMigration Plugin - Bootstrap
 *
 * @category  Shopware
 * @package   Shopware\Plugins\SwagMigration
 * @copyright Copyright (c), shopware AG (http://www.shopware.com)
 */
class Shopware_Plugins_Backend_SwagMigration_Bootstrap extends Shopware_Components_Plugin_Bootstrap
{
    /**
     * @var PDOConnection $db
     */
    private $db;

    /**
     * Install method of the plugin. Register the migration controller, create the backend menu item and creates the
     * plugin database table.
     *
     * @return array
     */
    public function install()
    {
        $this->checkVersion('5.0.0');
        $this->subscribeEvents();

        $parent = $this->Menu()->findOneBy(['label' => 'Inhalte']);
        $this->createMenuItem(
            [
                'label' => 'Shop-Migration',
                'class' => 'sprite-database-import',
                'active' => 1,
                'parent' => $parent,
                'position' => 0,
                'controller' => 'SwagMigration',
                'action' => 'Index'
            ]
        );

        $sql = '
			CREATE TABLE IF NOT EXISTS `s_plugin_migrations` (
			  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
			  `typeID` INT(11) UNSIGNED NOT NULL,
			  `sourceID` VARCHAR(255) NOT NULL,
			  `targetID` INT(11) UNSIGNED NOT NULL,
			  PRIMARY KEY (`id`),
			  UNIQUE KEY `typeID` (`typeID`,`sourceID`)
			) ENGINE=MyISAM  DEFAULT CHARSET=latin1;';
        $this->db->query($sql);

        $this->createForm();

        return ['success' => true, 'invalidateCache' => ['backend']];
    }

    /**
     * @param string $version
     * @throws Exception
     */
    public function checkVersion($version)
    {
        if (!$this->assertMinimumVersion($version)) {
            throw new Exception('This plugin requires Shopware ' . $version . ' or a later version');
        }
    }

    /**
     * Update the plugin to the current version
     *
     * @param string $version
     * @return array
     */
    public function update($version)
    {
        $this->checkVersion('5.0.0');
        $this->subscribeEvents();

        // Create form
        $this->createForm();

        // Clean up the migration table in order to not have duplicate entries
        $sql = '
		-- Remove non existing article references
		DELETE m FROM `s_plugin_migrations` m
		LEFT JOIN s_articles_details ad
			ON ad.id = m.targetID
		WHERE ad.id IS NULL AND typeID = 1;

		-- Remove non existing category references
		DELETE m FROM `s_plugin_migrations` m
		LEFT JOIN s_categories c
			ON c.id = m.targetID
		WHERE c.id IS NULL AND typeID IN (2,99);

		-- Remove non-existing customer references
		DELETE m FROM `s_plugin_migrations` m
		LEFT JOIN s_user u
			ON u.id = m.targetID
		WHERE u.id IS NULL AND typeID = 3;

		-- Remove non-existing order references
		DELETE m FROM `s_plugin_migrations` m
		LEFT JOIN s_order o
			ON o.id = m.targetID
		WHERE o.id IS NULL AND typeID = 4;

		-- Replace the old index
		ALTER TABLE  `s_plugin_migrations` DROP INDEX  `typeID` ,
		ADD UNIQUE  `typeID` (  `typeID` ,  `sourceID` );';

        try {
            $this->db->query($sql);
        } catch (\Exception $e) {
            // The above statement is just a cleanup statement, so errors should not cancel the whole update process
        }

        // Make sure that s_order_number is valid
        $sql = "
            INSERT IGNORE INTO `s_order_number` (`number`, `name`, `desc`) VALUES
            (30004, 'user', 'Kunden'),
            (30002, 'invoice', 'Bestellungen'),
            (30000, 'doc_1', 'Lieferscheine'),
            (30000, 'doc_2', 'Gutschriften'),
            (30000, 'doc_0', 'Rechnungen'),
            (20001, 'articleordernumber', 'Artikelbestellnummer  '),
            (20000, 'sSERVICE1', 'Service - 1'),
            (20000, 'sSERVICE2', 'Service - 2'),
            (210, 'blogordernumber', 'Blog - ID');";
        $this->db->query($sql);

        //Fix snippet
        $oldSnippet = "Die Produkt-Nummer '%s' ist ungültig. Eine gültige Nummer darf:<br>
            * höchstens 40 Zeichen lang sein<br>
            * keine anderen Zeichen als : 'a-zA-Z0-9-_. ' und SPACE beinhalten<br>
            <br>
            Sie können den Import dennoch erzwingen. Beachten Sie: <br>
            * Dabei werden zu lange Produkt-Nummern abgeschnitten. Dies kann zu 'Duplicate Key'-Fehlern führen<br>
            * Artikel mit ungültigen Nummern werden Sie später nur ändern und speichern können, wenn Sie dabei die Nummer anpassen<br>
        ";
        $newSnippet = "Die Produkt-Nummer '%s' ist ungültig. Eine gültige Nummer darf:<br>
            * höchstens 30 Zeichen lang sein<br>
            * keine anderen Zeichen als : 'a-zA-Z0-9-_. ' und SPACE beinhalten<br>
            <br>
            Sie können den Import dennoch erzwingen. Beachten Sie: <br>
            * Dabei werden zu lange Produkt-Nummern abgeschnitten. Dies kann zu 'Duplicate Key'-Fehlern führen<br>
            * Artikel mit ungültigen Nummern werden Sie später nur ändern und speichern können, wenn Sie dabei die Nummer anpassen<br>
        ";
        $sql = "UPDATE s_core_snippets SET `value` = ? WHERE `name` = ? AND `value` = ?";
        $this->db->query($sql, [$newSnippet, 'numberNotValid', $oldSnippet]);

        return true;
    }

    /**
     * Subscribe the needed events
     */
    public function subscribeEvents()
    {
        $this->subscribeEvent('Enlight_Controller_Front_DispatchLoopStartup', 'onStartDispatch');

        $this->subscribeEvent(
            'Enlight_Controller_Dispatcher_ControllerPath_Backend_SwagMigration',
            'onGetControllerPath'
        );

        $this->subscribeEvent('Shopware_Components_Password_Manager_AddEncoder', 'onAddPasswordEncoder');

        $this->subscribeEvent('Enlight_Controller_Action_PostDispatch', 'onPostDispatch', 110);
    }

    /**
     * add migration services
     */
    public function onStartDispatch()
    {
        $container = Shopware()->Container();
        $subscribers = [new Resources($container)];

        foreach ($subscribers as $subscriber) {
            $this->get('events')->addSubscriber($subscriber);
        }
    }

    /**
     * register namespace
     * initialise database connection
     */
    public function afterInit()
    {
        /** @var Enlight_Loader $loader */
        $loader = $this->get('loader');

        $loader->registerNamespace(
            'Shopware\SwagMigration',
            $this->Path()
        );

        $this->db = $this->get('db');
    }

    /**
     * Create the config form for the plugin
     */
    public function createForm()
    {
        $form = $this->Form();

        $form->setElement(
            'boolean',
            'debugMigration',
            [
                'description' => 'Soll eine Debug-Ausgabe geschrieben werden? Achtung! Kann die Geschwindigkeit des Imports negativ beeinflussen.',
                'label' => 'Debug-Ausgabe',
                'value' => false,
            ]
        );

        $translation = [
            'en_GB' => [
                'debugMigration' => [
                    'label' => 'Debug output',
                    'description' => 'Should a debug output be written? Attention! Could reduce the import speed.',
                ]
            ]
        ];

        $this->addFormTranslations($translation);
    }

    /**
     * Convenience function to register template and snippet dirs
     */
    protected function registerMyTemplateDir()
    {
        $this->get('snippets')->addConfigDir($this->Path() . 'Snippets/');
        $this->get('template')->addTemplateDir($this->Path() . 'Views/');
    }

    /**
     * Callback function to register our password encoders
     *
     * @param Enlight_Event_EventArgs $args
     * @return array
     */
    public function onAddPasswordEncoder(\Enlight_Event_EventArgs $args)
    {
        $hashes = $args->getReturn();

        $hashes[] = new Md5Reversed();

        return $hashes;
    }

    /**
     * Register template dir on time
     */
    public function onPostDispatch()
    {
        $this->registerMyTemplateDir();
    }

    /**
     * Uninstall method of the plugin. The plugin database table will be dropped.
     *
     * @return bool
     */
    public function uninstall()
    {
        $sql = 'DROP TABLE IF EXISTS `s_plugin_migrations`;';
        $this->db->query($sql);

        return parent::uninstall();
    }

    /**
     * Backend controller path event. Returns the path of the backend migration controller.
     *
     * @return string
     */
    public function onGetControllerPath()
    {
        $this->registerMyTemplateDir();

        return $this->Path() . 'Controllers/Backend/SwagMigration.php';
    }

    /**
     * Returns the meta information about the plugin
     * as an array.
     * Keep in mind that the plugin description located
     * in the info.txt.
     *
     * @return array
     */
    public function getInfo()
    {
        return [
            'version' => $this->getVersion(),
            'label' => $this->getLabel(),
            'author' => 'shopware AG',
            'description' => file_get_contents($this->Path() . 'info.txt'),
            'support' => 'http://forum.shopware.com/',
            'link' => 'http://forum.shopware.com/',
            'copyright' => 'shopware AG',
        ];
    }

    /**
     * Returns the version of the plugin as a string
     *
     * @return string
     * @throws Exception
     */
    public function getVersion()
    {
        $info = json_decode(file_get_contents(__DIR__ . DIRECTORY_SEPARATOR . 'plugin.json'), true);

        if ($info) {
            return $info['currentVersion'];
        } else {
            throw new Exception('The plugin has an invalid version file.');
        }
    }

    /**
     * Returns the well-formatted name of the plugin
     * as a sting
     *
     * @return string
     */
    public function getLabel()
    {
        return 'Shopware Migration';
    }
}
