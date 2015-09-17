<?php

namespace Shopware\SwagMigration\Subscriber;

use Enlight\Event\SubscriberInterface;
use Shopware\SwagMigration\Components\DbServices\DeleteService;
use Shopware\SwagMigration\Components\DbServices\Import\Import;

class Resources implements SubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return array(
            'Enlight_Bootstrap_InitResource_swagmigration.import' => 'onInitImport',
            'Enlight_Bootstrap_InitResource_swagmigration.deleteService' => 'onInitDeleteService',
        );
    }

    /**
     * @return Import
     */
    public function onInitImport()
    {
        return new Import();
    }

    /**
     * @return DeleteService
     */
    public function onInitDeleteService()
    {
        return new DeleteService();
    }
}