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
 * Shopware SwagMigration Components - Progress
 *
 * Progress class
 *
 * @category  Shopware
 * @package Shopware\Plugins\SwagMigration\Components
 * @copyright Copyright (c) 2012, shopware AG (http://www.shopware.de)
 */
class Shopware_Components_Migration_Import_Progress extends Enlight_Class
{
    const STATUS_ERROR = 1;
    const STATUS_DONE = 3;

    protected $message;
    protected $success;
    protected $offset;
    protected $count;
    protected $progress = null;
    protected $requestParams = array();
    protected $status = 0;

    protected $startTime;

    /**
     * Helper method which prints the current progress
     */
    public function printOutput()
    {
        // If $this->progress was set, use that value, else use the calculated value
        $progress = $this->getProgress() === null ? $this->getCalculatedProgress() : $this->getProgress();

        $output = array(
            'message' => $this->getMessage(),
            'success'=> $this->getSuccess(),
            'offset' => $this->getOffset(),
            'progress' => $progress,
            'estimated' => $this->getEstimation(),
            'task_start_time' => $this->getStartTime()
        );


        foreach ($this->getRequestParams() as $key => $value) {
            $output[$key] = $value;
        }

        echo Zend_Json::encode($output);
        return;
    }

    public function error($message) {
        $this->setMessage($message);
        $this->setSuccess(false);
        $this->setOffset(0);
        $this->setProgress(-1);
        $this->setStatus(self::STATUS_ERROR);

        return $this;
    }

    /**
     * @return $this
     */
    public function done()
    {
        $this->setSuccess(true);
        $this->setProgress(-1);
        $this->setOffset(0);
        $this->setStatus(self::STATUS_DONE);

        return $this;
    }

    public function getEstimation()
    {
//        (time()-$taskStartTime)/$offset * ($count-$offset)
        if ($this->count && $this->offset) {
            return (time()-$this->startTime)/$this->offset * ($this->count-$this->offset);
        }

        return -1;
    }

    public function setCount($count)
    {
        $this->count = $count;
    }

    public function getCount()
    {
        return $this->count;
    }

    public function setMessage($message)
    {
        $this->message = $message;
    }

    public function getMessage()
    {
        return $this->message;
    }

    public function setOffset($offset)
    {
        $this->offset = $offset;
    }

    public function getOffset()
    {
        return $this->offset;
    }

    public function increaseOffset()
    {
        return ++$this->offset;
    }

    public function setProgress($progress)
    {
        $this->progress = $progress;
    }

    public function getProgress()
    {
        return $this->progress;
    }

    public function getCalculatedProgress()
    {
        return $this->offset / $this->count;
    }


    public function getStartTime()
    {
        return $this->startTime;
    }

    public function setStartTime($startTime)
    {
        $this->startTime = $startTime;
    }


    public function setSuccess($success)
    {
        $this->success = $success;
    }

    public function getSuccess()
    {
        return $this->success;
    }

    public function setRequestParams($requestParams)
    {
        $this->requestParams = $requestParams;
    }

    public function getRequestParams()
    {
        return $this->requestParams;
    }


    public function addRequestParam($key, $value)
    {
        $this->requestParams[$key] = $value;
        return $this;
    }

    public function setStatus($status)
    {
        $this->status = $status;
    }

    public function getStatus()
    {
        return $this->status;
    }

    public function isError()
    {
        return self::STATUS_ERROR == $this->getStatus();
    }

    public function isDone()
    {
        return self::STATUS_DONE == $this->getStatus();
    }


}