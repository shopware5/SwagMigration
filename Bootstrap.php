<?php
/**
 * Bootstrap class of the plugin. Only used to register the controller, create the backend menu item and the plugin database table.
 */
class Shopware_Plugins_Backend_SwagMigration_Bootstrap extends Shopware_Components_Plugin_Bootstrap
{
    /**
     * Install method of the plugin. Register the migration controller, create the backend menu item and creates the plugin database table.
     * @return bool
     */
	public function install()
	{
		$this->subscribeEvent(
	 		'Enlight_Controller_Dispatcher_ControllerPath_Backend_SwagMigration',
	 		'onGetControllerPath'
	 	);


	 	$parent = $this->Menu()->findOneBy('label', 'Inhalte');
		$item = $this->createMenuItem(array(
			'label' => 'Shop-Migration',
            'class' => 'sprite-document-convert',
			'active' => 1,
			'parent' => $parent,
            'position' => 0,
            'controller' => 'SwagMigration',
            'action' => 'Index'
		));
		
		$this->Menu()->addItem($item);
		$this->Menu()->save();
		
		$sql = '
			CREATE TABLE IF NOT EXISTS `s_plugin_migrations` (
			  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
			  `typeID` int(11) unsigned NOT NULL,
			  `sourceID` varchar(255) NOT NULL,
			  `targetID` int(11) unsigned NOT NULL,
			  PRIMARY KEY (`id`),
			  UNIQUE KEY `typeID` (`typeID`,`sourceID`,`targetID`)
			) ENGINE=MyISAM  DEFAULT CHARSET=latin1;
		';
		Shopware()->Db()->query($sql);
		
	 	return true;
	}

    /**
     * Convenience function to register template and snippet dirs
     */
    protected function registerMyTemplateDir()
    {
//        $this->Application()->Snippets()->addConfigDir(
//            $this->Path() . 'Snippets/'
//        );
        $this->Application()->Template()->addTemplateDir(
            $this->Path() . 'Views/'
        );
    }

    /**
     * Uninstall method of the plugin. The plugin database table will be dropped.
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
     * @static
     * @param Enlight_Event_EventArgs $args
     * @return string
     */
	public function onGetControllerPath(Enlight_Event_EventArgs $args)
    {
        $this->registerMyTemplateDir();
        return $this->Path(). 'Controllers/Backend/SwagMigration.php';

    }

    /**
     * This function returns the file path of the meta information file.
     * @return mixed
     */
    public function getInfo()
    {
    	return include(dirname(__FILE__) . '/Meta.php');
    }


    public function getLabel()
    {
        $info = $this->getInfo();
        return $info['label'];
    }
}