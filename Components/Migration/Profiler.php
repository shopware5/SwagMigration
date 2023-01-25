<?php
/**
 * (c) shopware AG <info@shopware.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Shopware\SwagMigration\Components\Migration;

class Profiler extends \Zend_Db_Profiler
{
    public const TIME_SEPARATOR = \PHP_EOL . '<<<=======================>>>' . \PHP_EOL;

    public const LOG_SEPARATOR = \PHP_EOL . '::::::::::' . \PHP_EOL;

    public const SQL_SEPARATOR = \PHP_EOL . 'SQL' . \PHP_EOL;

    public const PARAMETER_SEPARATOR = \PHP_EOL . 'PARAMETER' . \PHP_EOL;

    public const TYPES_SEPARATOR = \PHP_EOL . 'TYPES' . \PHP_EOL;

    public const DURATION_SEPARATOR = \PHP_EOL . 'DURATION' . \PHP_EOL;

    public const END_SEPARATOR = \PHP_EOL . '<<<============ END OF QUERY ============>>>' . \PHP_EOL;

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
     */
    public function __construct($enabled = true, \Enlight_Components_Db_Adapter_Pdo_Mysql $connection)
    {
        $this->connection = $connection;

        parent::__construct($enabled);

        $migrationPath = Shopware()->Container()->getParameter('shopware.app.rootDir') . 'files/migration/';
        if (!\is_dir($migrationPath)) {
            \mkdir($migrationPath);
        }

        $dateTime = new \DateTime();
        $formattedDateTime = $dateTime->format('Y-m-d_H_i');
        $formattedTime = $dateTime->format('H:i:s');

        $initialSeparator = self::TIME_SEPARATOR . $formattedTime . self::TIME_SEPARATOR;

        $this->file = $migrationPath . $formattedDateTime . '.log.txt';

        \file_put_contents($this->file, $initialSeparator);
    }

    /**
     * @param int $id
     *
     * @return string
     */
    public function queryEnd($id)
    {
        $returnValue = parent::queryEnd($id);

        $profile = $this->getQueryProfile($id);

        $this->log($profile);

        return $returnValue;
    }

    private function log(\Zend_Db_Profiler_Query $profiler_Query)
    {
        if ($this->isExplaining) {
            return;
        }

        $stringToLog = self::SQL_SEPARATOR . $profiler_Query->getQuery() . \PHP_EOL;

        $stringToLog .= self::PARAMETER_SEPARATOR;
        foreach ($profiler_Query->getQueryParams() as $param) {
            $stringToLog .= \PHP_EOL . $param;
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

        $stringToLog .= \PHP_EOL;

        $stringToLog .= self::TYPES_SEPARATOR . $profiler_Query->getQueryType() . \PHP_EOL;

        $stringToLog .= self::DURATION_SEPARATOR . $profiler_Query->getElapsedSecs();

        $stringToLog .= self::END_SEPARATOR;

        \file_put_contents($this->file, $stringToLog, \FILE_APPEND);
    }

    /**
     * @param string $string
     *
     * @return string
     */
    private function getExplainString(array $array, $string)
    {
        $string .= \PHP_EOL . 'EXPLAIN' . \PHP_EOL;

        foreach ($array as $resultArray) {
            foreach ($resultArray as $key => $value) {
                $string .= $key . ' => ' . $value . \PHP_EOL;
            }
        }

        return $string;
    }
}
