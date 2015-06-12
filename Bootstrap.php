<?php
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
 * Shopware SwagMigration Plugin - Bootstrap
 *
 * @category  Shopware
 * @package   Shopware\Plugins\SwagMigration
 * @copyright Copyright (c) 2012, shopware AG (http://www.shopware.de)
 */
class Shopware_Plugins_Backend_SwagMigration_Bootstrap extends Shopware_Components_Plugin_Bootstrap
{
    /**
     * Install method of the plugin. Register the migration controller, create the backend menu item and creates the
     * plugin database table.
     *
     * @return bool
     */
    public function install()
    {
        $this->subscribeEvents();
        $this->checkVersion('4.3.0');

        $parent = $this->Menu()->findOneBy(array('label' => 'Inhalte'));
        $item = $this->createMenuItem(
            array(
                'label' => 'Shop-Migration',
                'class' => 'sprite-database-import',
                'active' => 1,
                'parent' => $parent,
                'position' => 0,
                'controller' => 'SwagMigration',
                'action' => 'Index'
            )
        );

        $this->Menu()->addItem($item);
        $this->Menu()->save();

        $sql = '
			CREATE TABLE IF NOT EXISTS `s_plugin_migrations` (
			  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
			  `typeID` INT(11) UNSIGNED NOT NULL,
			  `sourceID` VARCHAR(255) NOT NULL,
			  `targetID` INT(11) UNSIGNED NOT NULL,
			  PRIMARY KEY (`id`),
			  UNIQUE KEY `typeID` (`typeID`,`sourceID`)
			) ENGINE=MyISAM  DEFAULT CHARSET=latin1;
		';
        Shopware()->Db()->query($sql);

        $this->createForm();

        return array(
            'success' => true,
            'invalidateCache' => array('backend')
        );
    }

    public function checkVersion($version)
    {
        if (!$this->assertVersionGreaterThen($version)) {
            throw new \Exception('This plugin requires Shopware ' . $version . ' or a later version');
        }
    }

    /**
     * Update the plugin to the current version
     *
     * @param string $version
     * @return array|bool
     */
    public function update($version)
    {
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
		ADD UNIQUE  `typeID` (  `typeID` ,  `sourceID` );
		';

        try {
            Shopware()->Db()->query($sql);
        } catch (\Exception $e) {
            // The above statement is just a cleanup statement, so errors should not
            // cancel the whole update process
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
            (210, 'blogordernumber', 'Blog - ID');
        ";
        Shopware()->Db()->query($sql);

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
        Shopware()->Db()->query($sql, array($newSnippet, 'numberNotValid', $oldSnippet));

        return true;
    }

    /**
     * Subscribe the needed events
     */
    public function subscribeEvents()
    {
        $this->subscribeEvent(
            'Enlight_Controller_Dispatcher_ControllerPath_Backend_SwagMigration',
            'onGetControllerPath'
        );

        $this->subscribeEvent(
            'Shopware_Components_Password_Manager_AddEncoder',
            'onAddPasswordEncoder'
        );

        $this->subscribeEvent(
            'Enlight_Controller_Action_PostDispatch',
            'onPostDispatch',
            110
        );
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
            array(
                'description' => 'Soll eine Debug-Ausgabe geschrieben werden? Achtung! Kann die Geschwindigkeit des Imports negativ beeinflussen.',
                'label' => 'Debug-Ausgabe',
                'value' => false,
            )
        );
    }

    /**
     * Convenience function to register template and snippet dirs
     */
    protected function registerMyTemplateDir()
    {
        $this->Application()->Snippets()->addConfigDir(
            $this->Path() . 'Snippets/'
        );
        $this->Application()->Template()->addTemplateDir(
            $this->Path() . 'Views/'
        );
    }

    /**
     * Callback function to register our password encoders
     *
     * @param Enlight_Event_EventArgs $args
     * @return array
     */
    public function onAddPasswordEncoder(\Enlight_Event_EventArgs $args)
    {
        Shopware()->Loader()->registerNamespace('Shopware_Components', dirname(__FILE__) . '/Components/');

        $hashes = $args->getReturn();

        $hashes[] = new Shopware_Components_Migration_PasswordEncoder_Md5Reversed();

        return $hashes;
    }

    /**
     * Register template dir on time
     *
     * @param Enlight_Event_EventArgs $args
     */
    public function onPostDispatch(Enlight_Event_EventArgs $args)
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
        $sql = '
			DROP TABLE IF EXISTS `s_plugin_migrations`;
		';
        Shopware()->Db()->query($sql);

        return parent::uninstall();
    }

    /**
     * Backend controller path event. Returns the path of the backend migration controller.
     *
     * @static
     * @param Enlight_Event_EventArgs $args
     * @return string
     */
    public function onGetControllerPath(Enlight_Event_EventArgs $args)
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
        return array(
            'version' => $this->getVersion(),
            'label' => $this->getLabel(),
            'author' => 'shopware AG',
            'description' => file_get_contents($this->Path() . 'info.txt'),
            'support' => 'http://www.forum.shopware.de',
            'changes' => array(
                '1.3.1' => array(
                    'releasedate' => '2010-01-18',
                    'lines' => array(
                        'Solves some problems of gambio profile'
                    )
                ),
                '1.3.2' => array(
                    'releasedate' => '2010-01-20',
                    'lines' => array(
                        'Some bug fixes in customer import'
                    )
                ),
                '1.3.3' => array(
                    'releasedate' => '2010-01-21',
                    'lines' => array(
                        'Add fix for errors of long description'
                    )
                ),
                '1.3.4' => array(
                    'releasedate' => '2010-01-24',
                    'lines' => array(
                        'Add better handling for category import',
                        'Improved support for large databases'
                    )
                ),
                '1.3.5' => array(
                    'releasedate' => '2010-01-25',
                    'lines' => array(
                        'Fix the problem of long category text',
                        'Fix the problem if the country is not available'
                    )
                ),
                '2.0.0' => array(
                    'releasedate' => '2012-11-10',
                    'lines' => array(
                        'Prepared for Shopware 4'
                    )
                )
            ),
            'revision' => '7'
        );
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
