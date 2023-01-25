<?php
/**
 * (c) shopware AG <info@shopware.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Shopware\SwagMigration\Components;

use Enlight_Controller_Request_RequestHttp as Request;
use Psr\Log\LoggerInterface;
use Shopware\SwagMigration\Components\Migration\Import\Progress;
use Shopware\SwagMigration\Components\Migration\Import\Resource\AbstractResource;
use Shopware\SwagMigration\Components\Migration\Profile;

class Migration extends \Enlight_Class
{
    /*
     * Default constants for the mappings from the foreign IDs to Shopware IDs
     */
    public const MAPPING_ARTICLE = 1;
    public const MAPPING_CATEGORY = 2;
    public const MAPPING_CUSTOMER = 3;
    public const MAPPING_ORDER = 4;
    public const MAPPING_VALID_NUMBER = 23;
    public const MAPPING_CATEGORY_TARGET = 99;

    public const CATEGORY_LANGUAGE_SEPARATOR = '_LANG_';

    /**
     * Namespace for the profiles
     *
     * @var string
     */
    protected static $profileNamespace = Profile::class;

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
     * @param string $profile
     *
     * @return \Enlight_Class
     */
    public static function profileFactory($profile, array $config = [])
    {
        $profileNamespace = empty($config['profileNamespace']) ? self::$profileNamespace : $config['profileNamespace'];

        $profileName = $profileNamespace . '\\';
        $profileName .= \str_replace(' ', '', \ucwords(\str_replace('_', ' ', \strtolower($profile))));

        return \Enlight_Class::Instance($profileName, [$config]);
    }

    /**
     * Generates an instances of an import resource
     *
     * @param string          $name
     * @param Progress        $progress
     * @param Profile         $source
     * @param Profile         $target
     * @param Request         $request
     * @param LoggerInterface $logger
     *
     * @return AbstractResource
     */
    public static function resourceFactory($name, $progress, $source, $target, $request, $logger)
    {
        $className = self::$resourceNamespace . '\\' . $name;

        $import = \Enlight_Class::Instance(
            $className,
            [
                $progress,
                $source,
                $target,
                $request,
                $logger,
                Shopware()->Container()->get('config'),
            ]
        );

        return $import;
    }
}
