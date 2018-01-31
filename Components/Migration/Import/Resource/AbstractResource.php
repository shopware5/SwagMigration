<?php
/**
 * (c) shopware AG <info@shopware.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Shopware\SwagMigration\Components\Migration\Import\Resource;

use Enlight_Class;
use Shopware\SwagMigration\Components\Migration;
use Shopware\SwagMigration\Components\Migration\Import\Progress;
use Shopware\SwagMigration\Components\Migration\Profile;

abstract class AbstractResource extends Enlight_Class implements ResourceInterface
{
    /**
     * Internal name of the import step used by the controller
     *
     * @var
     */
    public $internal_name;

    /**
     * References the progress object
     *
     * @var Progress
     */
    protected $progress;

    /**
     * When was the request started?
     *
     * @var int
     */
    protected $requestTime;

    /**
     * How long may the request take at most?
     *
     * @var int
     */
    protected $maxExecution;

    /**
     * Request object
     *
     * @var
     */
    protected $request;

    /**
     * Source-Object
     *
     * @var Profile
     */
    protected $source;

    /**
     * Target-Object
     *
     * @var Profile
     */
    protected $target;

    /**
     * Constructor
     *
     * @param Progress $progress
     * @param Profile  $source
     * @param Profile  $target
     * @param $request
     */
    public function __construct($progress, $source, $target, $request)
    {
        $this->progress = $progress;
        $this->target = $target;
        $this->source = $source;
        $this->requestTime = !empty($_SERVER['REQUEST_TIME']) ? $_SERVER['REQUEST_TIME'] : time();
        $this->request = $request;
        parent::__construct();
    }

    /**
     * Get the snippet object
     *
     * @return mixed
     */
    public function getNameSpace()
    {
        return Shopware()->Snippets()->getNamespace('backend/swag_migration/main');
    }

    /**
     * Check if a new request is needed (in order not to brake the max_execution_time)
     *
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
     * Set the progress object for this instance
     *
     * @param $progress
     */
    public function setProgress($progress)
    {
        $this->progress = $progress;
    }

    /**
     * Return the progress instance of this class
     *
     * @return Progress
     */
    public function getProgress()
    {
        return $this->progress;
    }

    /**
     * Set the time of the request
     *
     * @param $requestTime
     */
    public function setRequestTime($requestTime)
    {
        $this->requestTime = $requestTime;
    }

    /**
     * Get the time of the request
     *
     * @return int
     */
    public function getRequestTime()
    {
        return $this->requestTime;
    }

    /**
     * Set the max execution time. Needed for the newRequestNeeded() check
     *
     * @param $maxExecution
     */
    public function setMaxExecution($maxExecution)
    {
        $this->maxExecution = $maxExecution;
    }

    /**
     * Get the max_execution time
     *
     * @return int
     */
    public function getMaxExecution()
    {
        return $this->maxExecution;
    }

    /**
     * Legacy getter for the request object
     *
     * @return mixed
     */
    public function Request()
    {
        return $this->request;
    }

    /**
     * Legacy getter for the source profile
     *
     * @return Profile
     */
    public function Source()
    {
        return $this->source;
    }

    /**
     * Legacy getter for the target profile
     *
     * @return Profile
     */
    public function Target()
    {
        return $this->target;
    }

    /**
     * Increase the progress by one
     *
     * @return mixed
     */
    public function increaseProgress()
    {
        return $this->getProgress()->increaseOffset();
    }

    /**
     * Setter for the internal name of the current resource
     *
     * @param $internal_name
     */
    public function setInternalName($internal_name)
    {
        $this->internal_name = $internal_name;
    }

    /**
     * Getter for the internal name of the current resource
     *
     * @return mixed
     */
    public function getInternalName()
    {
        return $this->internal_name;
    }

    /**
     * Takes an invalid product number and creates a valid one from it
     * by returning its md5 hash
     *
     * @param $number int The invalid ordernumber to fix
     * @param $id int Id of the article
     *
     * @return string
     */
    public function makeInvalidNumberValid($number, $id)
    {
        // Look up the id in the database - perhaps we've already created a valid number:
        $number = Shopware()->Db()->fetchOne(
            'SELECT targetID FROM s_plugin_migrations WHERE typeID = ? AND sourceID = ?',
            [Migration::MAPPING_VALID_NUMBER, $id]
        );

        if ($number) {
            return Shopware()->Config()->backendAutoOrderNumberPrefix . $number;
        }

        // Get number
        $number = (int) Shopware()->Db()->fetchOne(
            'SELECT `number` FROM `s_order_number` WHERE `name`="articleordernumber" FOR UPDATE;'
        );

        // Increase - save
        $sql = 'UPDATE s_order_number SET number = ? WHERE name = ?';
        Shopware()->Db()->query($sql, [++$number, 'articleordernumber']);

        // Save mapping
        Shopware()->Db()->insert(
            's_plugin_migrations',
            [
                'typeID' => Migration::MAPPING_VALID_NUMBER,
                'sourceID' => $id,
                'targetID' => $number,
            ]
        );

        return Shopware()->Config()->backendAutoOrderNumberPrefix . $number;
    }

    /**
     * Returns a SW-productID for a given source-productId
     *
     * @param $productId
     *
     * @return string
     */
    public function getBaseArticleInfo($productId)
    {
        $sql = '
            SELECT
                ad.articleID AS productId
            FROM s_plugin_migrations pm
            LEFT JOIN s_articles_details ad
                ON ad.id = pm.targetID
            WHERE pm.`sourceID`=?
            AND (`typeID`=? OR `typeID`=?)
        ';

        return Shopware()->Db()->fetchOne(
            $sql,
            [
                $productId,
                Migration::MAPPING_ARTICLE,
                Migration::MAPPING_VALID_NUMBER,
            ]
        );
    }
}
