<?php

/**
 * Shopware Migration Component is an extension of the enlight class.
 * This class has an factory to create the profile object for runtime
 *
 * @copyright Copyright (c) 2011, Shopware AG
 * @author o.denter
 * @package Shopware
 * @subpackage Controllers_Frontend
 * @copyright Copyright (c) 2011, shopware AG
 * @version
 */
class Shopware_Components_Migration extends Enlight_Class
{
	static protected $profileNamespace = 'Shopware_Components_Migration_Profile';

    /**
     * For the generation of the profile is a factory used, because of the profile type is not known until runtime.
     * @static
     * @param $profile
     * @param array $config
     * @return Enlight_Class
     */
	public static function factory($profile, $config = array())
	{
		$profileNamespace = empty($config['profileNamespace']) ? self::$profileNamespace : $config['profileNamespace'];
		$profileName = $profileNamespace . '_';
		$profileName .= str_replace(' ', '', ucwords(str_replace('_', ' ', strtolower($profile))));
				
		$migrationAdapter = Enlight_Class::Instance($profileName, array($config));
		
		return $migrationAdapter;
	}
}