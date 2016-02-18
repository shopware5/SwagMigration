<?php
/**
 * (c) shopware AG <info@shopware.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Shopware\SwagMigration\Components\Migration\Import\Resource;

use Shopware\SwagMigration\Components\Migration\Import\Progress;

interface ResourceInterface
{
    /**
     * Returns the default error message for this import class
     *
     * @return string
     */
    public function getDefaultErrorMessage();

    /**
     * Returns the progress message for the current import step
     * a Progress object will be passed, so you can get some context info for your snippet
     *
     * @param Progress $progress
     * @return string
     */
    public function getCurrentProgressMessage(Progress $progress);

    /**
     * Done message of your importer
     *
     * @return string
     */
    public function getDoneMessage();

    /**
     * Main run method of each import adapter. The run method will query the source profile, iterate
     * the results and prepare the data for import via the old Shopware API.
     *
     * If you want to import multiple entities with one import-class, you might want to check for
     * $this->getInternalName() in order to distinct which (sub)entity you where called for.
     *
     * The run method should only return instances of Progress
     * The calling instance will use those progress object to communicate with the ExtJS backend.
     * If you want this to work properly, think of calling:
     * - $this->initTaskTimer() at the beginning of your run method
     * - $this->getProgress()->setCount(222) to set the total number of data
     * - $this->increaseProgress() to increase the offset/progress
     * - $this->getProgress()->getOffset() to get the current progress' offset
     * - return $this->getProgress()->error("Message") in order to stop with an error message
     * - return $this->getProgress() in order to be called again with the current offset
     * - return $this->getProgress()->done() in order to mark the import as finished
     *
     * @return Progress
     */
    public function run();
}
