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

namespace Shopware\SwagMigration\Subscriber;

use Enlight\Event\SubscriberInterface;
use Shopware\Components\DependencyInjection\Container;
use Shopware\SwagMigration\Components\DbServices\DeleteService;
use Shopware\SwagMigration\Components\DbServices\Import\Import;

class Resources implements SubscriberInterface
{
    /** @var Container $container*/
    private $container;

    /**
     * Resources constructor.
     *
     * @param Container $container
     */
    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /**
     * @inheritdoc
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
