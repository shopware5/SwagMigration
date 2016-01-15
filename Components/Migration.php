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
 * Shopware SwagMigration Components - Migration
 *
 * Factory for the migration profiles and import resources
 *
 * @category Shopware
 * @package Shopware\Plugins\SwagMigration\Components
 * @copyright Copyright (c) 2012, shopware AG (http://www.shopware.de)
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
     *
     * @var string
     */
    protected static $profileNamespace = 'Shopware_Components_Migration_Profile';

    /**
     * Namespace for the import resources
     *
     * @var string
     */
    protected static $resourceNamespace = 'Shopware_Components_Migration_Import_Resource';

    /**
     * For the generation of the profile is a factory used, because of the profile type is not known until runtime.
     *
     * @static
     * @param $profile
     * @param array $config
     * @return Enlight_Class
     */
    public static function profileFactory($profile, $config = [])
    {
        $profileNamespace = empty($config['profileNamespace']) ? self::$profileNamespace : $config['profileNamespace'];
        $profileName = $profileNamespace . '_';
        $profileName .= str_replace(' ', '', ucwords(str_replace('_', ' ', strtolower($profile))));

        $migrationAdapter = Enlight_Class::Instance($profileName, [$config]);

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
        $import = Enlight_Class::Instance(
            $className,
            [
                $progress,
                $source,
                $target,
                $request
            ]
        );

        return $import;
    }
}
