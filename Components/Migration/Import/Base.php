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
 * Shopware SwagMigration Components - Base
 *
 * Base import
 *
 * @category  Shopware
 * @package Shopware\Plugins\SwagMigration\Components
 * @copyright Copyright (c) 2012, shopware AG (http://www.shopware.de)
 */
abstract class Shopware_Components_Migration_Import_Base extends Enlight_Class implements Shopware_Components_Migration_Import_Interface
{
    /**
     * Internal name of the import step used by the controller
     * @var
     */
    public $internal_name;

    /**
     * References the progress object
     * @var Shopware_Components_Migration_Import_Progress
     */
    protected $progress;

    /**
     * When was the request started?
     * @var int
     */
    protected $requestTime;

    /**
     * How long may the request take at most?
     * @var int
     */
    protected $maxExecution;

    /**
     * Request object
     * @var
     */
    protected $request;

    /**
     * Source-Object
     * @var Shopware_Components_Migration_Profile
     */
    protected $source;

    /**
     * @param Shopware_Components_Migration_Import_Progress $progress
     * @param Shopware_Components_Migration_Profile $source
     * @param int $maxExecution
     * @param $request
     */
    public function __construct($progress, $source, $maxExecution, $request)
    {
        $this->progress = $progress;
        $this->source = $source;
        $this->requestTime =  !empty($_SERVER['REQUEST_TIME']) ? $_SERVER['REQUEST_TIME'] : time();
        $this->maxExecution = $maxExecution;
        $this->request = $request;
    }

    /**
     * Get the snippet object
     * @return mixed
     */
    public function getNameSpace()
    {
        return Shopware()->Snippets()->getNamespace('backend/swag_migration/main');
    }

    /**
     * Check if a new request is needed (in order not to brake the max_execution_time)
     * @return bool
     */
    public function newRequestNeeded()
    {
        if (time() - $this->getRequestTime() >= $this->getMaxExecution()) {
            return true;
        }

        return false;
    }


    /**
     * Helper function which sets the start time for a given task
     *
     * Timer should be inited after the inital DB-Query of each task, as this
     * query will usually take longer on the first run and therefore distort the results
     *
     * @return int
     */
    public function initTaskTimer()
    {
        $startTime = (int) $this->Request()->getParam('task_start_time', 0);
        $offset = empty($this->Request()->offset) ? 0 : (int) $this->Request()->offset;

        if ($startTime == 0 || $offset == 0) {
            $startTime = time();
        }

        $this->Request()->setParam('task_start_time', $startTime);

        $this->getProgress()->setStartTime($startTime);

        return $startTime;
    }

    /**
     * @param $progress
     */
    public function setProgress($progress)
    {
        $this->progress = $progress;
    }

    /**
     * @return Shopware_Components_Migration_Import_Progress
     */
    public function getProgress()
    {
        return $this->progress;
    }

    /**
     * @param $requestTime
     */
    public function setRequestTime($requestTime)
    {
        $this->requestTime = $requestTime;
    }

    /**
     * @return int
     */
    public function getRequestTime()
    {
        return $this->requestTime;
    }

    /**
     * @param $maxExecution
     */
    public function setMaxExecution($maxExecution)
    {
        $this->maxExecution = $maxExecution;
    }

    /**
     * @return int
     */
    public function getMaxExecution()
    {
        return $this->maxExecution;
    }

    /**
     * @return mixed
     */
    public function Request()
    {
        return $this->request;
    }

    /**
     * @return Shopware_Components_Migration_Profile
     */
    public function Source()
    {
        return $this->source;
    }

    /**
     * @return mixed
     */
    public function increaseProgress()
    {
        return $this->getProgress()->increaseOffset();
    }

    /**
     * @param $internal_name
     */
    public function setInternalName($internal_name)
    {
        $this->internal_name = $internal_name;
    }

    /**
     * @return mixed
     */
    public function getInternalName()
    {
        return $this->internal_name;
    }

}