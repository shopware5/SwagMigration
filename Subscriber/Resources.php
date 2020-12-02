<?php
/**
 * (c) shopware AG <info@shopware.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Shopware\SwagMigration\Subscriber;

use Enlight\Event\SubscriberInterface;
use Shopware\Components\DependencyInjection\Container;
use Shopware\SwagMigration\Components\DbServices\DeleteService;
use Shopware\SwagMigration\Components\DbServices\Import\Import;

class Resources implements SubscriberInterface
{
    /**
     * @var Container
     */
    private $container;

    /**
     * Resources constructor.
     */
    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return [
            'Enlight_Bootstrap_InitResource_swagmigration.import' => 'onInitImport',
            'Enlight_Bootstrap_InitResource_swagmigration.deleteService' => 'onInitDeleteService',
        ];
    }

    /**
     * @return Import
     */
    public function onInitImport()
    {
        return new Import($this->container);
    }

    /**
     * @return DeleteService
     */
    public function onInitDeleteService()
    {
        return new DeleteService($this->container->get('db'));
    }
}
