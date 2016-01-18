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
 * Shopware SwagMigration Components - Progress
 *
 * The progress class abstracts the communication between the import tasks and the ExtJs application.
 * The attributes $message, $success, $offset and $count will be used to display the actual progress.
 *
 * As the progress class basically wraps a HttpResponse and prints the data as a Json-object, you might
 * want to add custom information by using addRequestParam().
 *
 * Keep in mind,that the ExtJs application will forward all received information back to the php controller;
 * for this reason, addRequestParam('hi', true) will result in a request-var "hi' being available within
 * the next request.
 *
 * @category  Shopware
 * @package Shopware\Plugins\SwagMigration\Components\Migration\Import
 * @copyright Copyright (c) 2012, shopware AG (http://www.shopware.de)
 */
class Shopware_Components_Migration_Import_Progress extends Enlight_Class
{
    const STATUS_ERROR = 1;
    const STATUS_DONE = 3;

    /**
     * Progress message
     *
     * @var string
     */
    protected $message;
    /**
     * Progress successful?
     *
     * @var int
     */
    protected $success;
    /**
     * Progress offset
     *
     * @var int
     */
    protected $offset;
    /**
     * Total number of items
     *
     * @var int
     */
    protected $count;
    /**
     * Progress. If not set, the progress is calculated dynamically
     *
     * @var null|float
     */
    protected $progress = null;
    /**
     * Additional request params
     *
     * @var array
     */
    protected $requestParams = [];
    /**
     * Progress status
     *
     * @var 0, STATUS_ERROR or STATUS_DONE
     */
    protected $status = 0;
    /**
     * When did the progress start
     *
     * @var int
     */
    protected $startTime;

    /**
     * Helper method which prints the current progress
     */
    public function printOutput()
    {
        // If $this->progress was set, use that value, else use the calculated value
        $progress = $this->getProgress() === null ? $this->getCalculatedProgress() : $this->getProgress();

        $output = [
            'message' => $this->getMessage(),
            'success' => $this->getSuccess(),
            'offset' => $this->getOffset(),
            'progress' => $progress,
            'estimated' => $this->getEstimation(),
            'task_start_time' => $this->getStartTime()
        ];


        foreach ($this->getRequestParams() as $key => $value) {
            $output[$key] = $value;
        }

        echo Zend_Json::encode($output);

        return;
    }

    /**
     * Abort the progress with an error
     *
     * @param $message
     * @return $this
     */
    public function error($message)
    {
        $this->setMessage($message);
        $this->setSuccess(false);
        $this->setOffset(0);
        $this->setProgress(-1);
        $this->setStatus(self::STATUS_ERROR);

        return $this;
    }

    /**
     * Finish progress successful
     *
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

    /**
     * Estimate time to finish the current task
     *
     * @return float|int
     */
    public function getEstimation()
    {
        if ($this->count && $this->offset) {
            return (time() - $this->startTime) / $this->offset * ($this->count - $this->offset);
        }

        return -1;
    }

    /**
     * Set the total number of items to import
     *
     * @param $count
     */
    public function setCount($count)
    {
        $this->count = $count;
    }

    /**
     * Return the number of items to import
     *
     * @return mixed
     */
    public function getCount()
    {
        return $this->count;
    }

    /**
     * Set the progress message
     *
     * @param $message
     */
    public function setMessage($message)
    {
        $this->message = $message;
    }

    /**
     * Get the progress message
     *
     * @return mixed
     */
    public function getMessage()
    {
        return $this->message;
    }

    /**
     * Set progress offset
     *
     * @param $offset
     */
    public function setOffset($offset)
    {
        $this->offset = $offset;
    }

    /**
     * Get progress offset
     *
     * @return mixed
     */
    public function getOffset()
    {
        return $this->offset;
    }

    /**
     * Increase the progress offset by one
     *
     * @return mixed
     */
    public function increaseOffset()
    {
        return ++$this->offset;
    }

    /**
     * Set the progress
     *
     * @param $progress
     */
    public function setProgress($progress)
    {
        $this->progress = $progress;
    }

    /**
     * Get progress
     *
     * @return null
     */
    public function getProgress()
    {
        return $this->progress;
    }

    /**
     * Calculate the progress by offset and total items
     *
     * @return float
     */
    public function getCalculatedProgress()
    {
        return $this->offset / $this->count;
    }

    /**
     * Return start time of the progress
     *
     * @return mixed
     */
    public function getStartTime()
    {
        return $this->startTime;
    }

    /**
     * Set the start time of the progress
     *
     * @param $startTime
     */
    public function setStartTime($startTime)
    {
        $this->startTime = $startTime;
    }

    /**
     * Set success
     *
     * @param $success
     */
    public function setSuccess($success)
    {
        $this->success = $success;
    }

    /**
     * Return progress' success
     *
     * @return mixed
     */
    public function getSuccess()
    {
        return $this->success;
    }

    /**
     * Set request params
     *
     * @param $requestParams
     */
    public function setRequestParams($requestParams)
    {
        $this->requestParams = $requestParams;
    }

    /**
     * Get request params
     *
     * @return array
     */
    public function getRequestParams()
    {
        return $this->requestParams;
    }

    /**
     * Add a single request param
     *
     * @param $key
     * @param $value
     * @return $this
     */
    public function addRequestParam($key, $value)
    {
        $this->requestParams[$key] = $value;

        return $this;
    }

    /**
     * Set the progress status
     *
     * @param $status
     */
    public function setStatus($status)
    {
        $this->status = $status;
    }

    /**
     * Get the progress' status
     *
     * @return int
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * Check if there was an error
     *
     * @return bool
     */
    public function isError()
    {
        return self::STATUS_ERROR == $this->getStatus();
    }

    /**
     * Check if the operation is done
     *
     * @return bool
     */
    public function isDone()
    {
        return self::STATUS_DONE == $this->getStatus();
    }
}
