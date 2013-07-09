<?php

/**
 * Basic interface for the import classes
 * Class Shopware_Components_Migration_Import_Resource_Interface
 */
interface Shopware_Components_Migration_Import_Resource_Interface
{
    /**
     * The generic error message of an import
     * @return string
     */
    public function getDefaultErrorMessage();

    /**
     * The progress message of your import. For progress info check out the $progress parameter
     * @param $progress
     * @return string
     */
    public function getCurrentProgressMessage($progress);

    /**
     * Done message of your importer
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
     * The run method should only return instances of Shopware_Components_Migration_Import_Progress
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
     *
     * @return Shopware_Components_Migration_Import_Progress
     */
    public function run();

}