<?php
/**
 * (c) shopware AG <info@shopware.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Shopware\SwagMigration\Components\Migration\Import;

class Progress extends \Enlight_Class
{
    public const STATUS_ERROR = 1;
    public const STATUS_DONE = 3;

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
     * @var float|null
     */
    protected $progress;

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
            'task_start_time' => $this->getStartTime(),
        ];

        foreach ($this->getRequestParams() as $key => $value) {
            $output[$key] = $value;
        }

        echo \Zend_Json::encode($output);
    }

    /**
     * Abort the progress with an error
     *
     * @param string $message
     *
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
            return (\time() - $this->startTime) / $this->offset * ($this->count - $this->offset);
        }

        return -1;
    }

    /**
     * Set the total number of items to import
     *
     * @param int $count
     */
    public function setCount($count)
    {
        $this->count = $count;
    }

    /**
     * Return the number of items to import
     *
     * @return int
     */
    public function getCount()
    {
        return $this->count;
    }

    /**
     * Set the progress message
     *
     * @param string $message
     */
    public function setMessage($message)
    {
        $this->message = $message;
    }

    /**
     * Get the progress message
     *
     * @return string
     */
    public function getMessage()
    {
        return $this->message;
    }

    /**
     * Set progress offset
     *
     * @param int $offset
     */
    public function setOffset($offset)
    {
        $this->offset = $offset;
    }

    /**
     * Get progress offset
     *
     * @return int
     */
    public function getOffset()
    {
        return $this->offset;
    }

    /**
     * Increase the progress offset by one
     *
     * @return int
     */
    public function increaseOffset()
    {
        return ++$this->offset;
    }

    /**
     * Set the progress
     *
     * @param float|null $progress
     */
    public function setProgress($progress)
    {
        $this->progress = $progress;
    }

    /**
     * @return float|null
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
     * @return int
     */
    public function getStartTime()
    {
        return $this->startTime;
    }

    /**
     * Set the start time of the progress
     *
     * @param int $startTime
     */
    public function setStartTime($startTime)
    {
        $this->startTime = $startTime;
    }

    /**
     * @param int $success
     */
    public function setSuccess($success)
    {
        $this->success = $success;
    }

    /**
     * Return progress' success
     *
     * @return int
     */
    public function getSuccess()
    {
        return $this->success;
    }

    /**
     * Set request params
     *
     * @param array $requestParams
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
     * @param string $key
     *
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
     * @param int $status
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
        return $this->getStatus() == self::STATUS_ERROR;
    }

    /**
     * Check if the operation is done
     *
     * @return bool
     */
    public function isDone()
    {
        return $this->getStatus() == self::STATUS_DONE;
    }
}
