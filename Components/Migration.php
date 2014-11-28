<?php
/*
 * (c) shopware AG <info@shopware.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Shopware SwagMigration Components - Migration
 *
 * Factory for the migration profiles and import resources
 */
class Shopware_Components_Migration extends Enlight_Class
{
    /*
     * Default constants for the mappings from the foreign IDs to Shopware IDs
     */
    const MAPPING_ARTICLE = 1;
    const MAPPING_CATEGORY = 2;
    const MAPPING_CUSTOMER = 3;
    const MAPPING_ORDER = 4;
    const MAPPING_VALID_NUMBER = 23;
    const MAPPING_CATEGORY_TARGET = 99;

    const CATEGORY_LANGUAGE_SEPARATOR = '_LANG_';

    /**
     * Namespace for the profiles
     * @var string
     */
    static protected $profileNamespace = 'Shopware_Components_Migration_Profile';

    /**
     * Namespace for the import resources
     * @var string
     */
    static protected $resourceNamespace = 'Shopware_Components_Migration_Import_Resource';

    /**
     * For the generation of the profile is a factory used, because of the profile type is not known until runtime.
     *
     * @static
     * @param $profile
     * @param array $config
     * @return Enlight_Class
     */
	public static function profileFactory($profile, $config = array())
	{
		$profileNamespace = empty($config['profileNamespace']) ? self::$profileNamespace : $config['profileNamespace'];
		$profileName = $profileNamespace . '_';
		$profileName .= str_replace(' ', '', ucwords(str_replace('_', ' ', strtolower($profile))));

		$migrationAdapter = Enlight_Class::Instance($profileName, array($config));

		return $migrationAdapter;
	}


    /**
     * Generates an instances of an import resource
     *
     * @param $name
     * @param $progress
     * @param $source
     * @param $target
     * @param $request
     * @return Shopware_Components_Migration_Import_Resource_Abstract
     */
    public static function resourceFactory($name, $progress, $source, $target, $request)
    {
        /** @var $import Shopware_Components_Migration_Import_Resource_Abstract */
        $className = self::$resourceNamespace . '_' . $name;
        $import = Enlight_Class::Instance($className, array(
            $progress,
            $source, $target, $request
        ));

        return $import;
    }


}
