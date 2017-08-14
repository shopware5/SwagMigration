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


namespace Shopware\SwagMigration\Components\Migration;

class Profiler extends \Zend_Db_Profiler
{
    const TIME_SEPARATOR = PHP_EOL . '<<<=======================>>>' . PHP_EOL;

    const LOG_SEPARATOR = PHP_EOL . '::::::::::' . PHP_EOL;

    const SQL_SEPARATOR = PHP_EOL . 'SQL' . PHP_EOL;

    const PARAMETER_SEPARATOR = PHP_EOL . 'PARAMETER' . PHP_EOL;

    const TYPES_SEPARATOR = PHP_EOL . 'TYPES' . PHP_EOL;

    const DURATION_SEPARATOR = PHP_EOL . 'DURATION' . PHP_EOL;

    const END_SEPARATOR = PHP_EOL . '<<<============ END OF QUERY ============>>>' . PHP_EOL;

    /**
     * @var string
     */
    private $file;

    /**
     * @var bool
     */
    private $isExplaining;

    /**
     * @var \Enlight_Components_Db_Adapter_Pdo_Mysql
     */
    private $connection;

    /**
     * @param bool $enabled
     * @param \Enlight_Components_Db_Adapter_Pdo_Mysql $connection
     */
    public function __construct($enabled = true, \Enlight_Components_Db_Adapter_Pdo_Mysql $connection)
    {
        $this->connection = $connection;

        parent::__construct($enabled);

        $migrationPath = Shopware()->DocPath('files_migration');

        if (!is_dir($migrationPath)) {
            mkdir($migrationPath);
        }

        $dateTime = new \DateTime();
        $formattedDateTime = $dateTime->format('Y-m-d H:i');
        $formattedTime = $dateTime->format('H:i:s');

        $initialSeparator = self::TIME_SEPARATOR . $formattedTime . self::TIME_SEPARATOR;

        $this->file = $migrationPath . $formattedDateTime . '.log.txt';

        file_put_contents($this->file, $initialSeparator);
    }

    /**
     * @param int $id
     * @return string
     */
    public function queryEnd($id)
    {
        $returnValue = parent::queryEnd($id);

        $profile = $this->getQueryProfile($id);

        $this->log($profile);

        return $returnValue;
    }

    /**
     * @param \Zend_Db_Profiler_Query $profiler_Query
     */
    private function log(\Zend_Db_Profiler_Query $profiler_Query)
    {
        if ($this->isExplaining) {
            return;
        }

        $stringToLog = self::SQL_SEPARATOR . $profiler_Query->getQuery() . PHP_EOL;

        $stringToLog .= self::PARAMETER_SEPARATOR;
        foreach ($profiler_Query->getQueryParams() as $param) {
            $stringToLog .= PHP_EOL . $param;
        }

        try {
            $this->isExplaining = true;
            $explainResult = $this->connection->fetchAll(
                'EXPLAIN ' . $profiler_Query->getQuery(),
                $profiler_Query->getQueryParams()
            );

            $stringToLog = $this->getExplainString($explainResult, $stringToLog);
        } catch (\Exception $exception) {
            // Do nothing
        } finally {
            $this->isExplaining = false;
        }

        $stringToLog .= PHP_EOL;

        $stringToLog .= self::TYPES_SEPARATOR . $profiler_Query->getQueryType() . PHP_EOL;

        $stringToLog .= self::DURATION_SEPARATOR . $profiler_Query->getElapsedSecs();

        $stringToLog .= self::END_SEPARATOR;

        file_put_contents($this->file, $stringToLog, FILE_APPEND);
    }

    /**
     * @param array $array
     * @param $string
     * @return string
     */
    private function getExplainString(array $array, $string)
    {
        $string .= PHP_EOL . 'EXPLAIN' . PHP_EOL;

        foreach ($array as $resultArray) {
            foreach ($resultArray as $key => $value) {
                $string .= $key . ' => ' . $value . PHP_EOL;
            }
        }

        return $string;
    }

}