<?php
/**
 * (c) shopware AG <info@shopware.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Shopware\SwagMigration\Components;

use Shopware\SwagMigration\Components\Migration\Import\Resource\AbstractResource;
use Shopware\SwagMigration\Components\Migration\Profile;

class Migration extends \Enlight_Class
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
    protected static $profileNamespace = 'Shopware\SwagMigration\Components\Migration\Profile';

    /**
     * Namespace for the import resources
     *
     * @var string
     */
    protected static $resourceNamespace = 'Shopware\SwagMigration\Components\Migration\Import\Resource';

    /**
     * For the generation of the profile is a factory used, because of the profile type is not known until runtime.
     *
     * @static
     *
     * @param $profile
     * @param array $config
     *
     * @return \Enlight_Class
     */
    public static function profileFactory($profile, $config = [])
    {
        $profileNamespace = empty($config['profileNamespace']) ? self::$profileNamespace : $config['profileNamespace'];

        $profileName = $profileNamespace . '\\';
        $profileName .= str_replace(' ', '', ucwords(str_replace('_', ' ', strtolower($profile))));

        $migrationAdapter = \Enlight_Class::Instance($profileName, [$config]);

        return $migrationAdapter;
    }

    /**
     * Generates an instances of an import resource
     *
     * @param string $name
     * @param $progress
     * @param Profile $source
     * @param Profile $target
     * @param $request
     *
     * @return AbstractResource
     */
    public static function resourceFactory($name, $progress, $source, $target, $request)
    {
        $className = self::$resourceNamespace . '\\' . $name;

        $import = \Enlight_Class::Instance(
            $className,
            [
                $progress,
                $source,
                $target,
                $request,
            ]
        );

        return $import;
    }
}
