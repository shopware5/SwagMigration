<?php
/**
 * (c) shopware AG <info@shopware.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Shopware\SwagMigration\Components\Migration;

use Doctrine\DBAL\Connection;
use Exception;

class DbDecorator extends Connection
{
    /**
     * The decorated Zend_Db instance
     *
     * @var
     */
    protected $instance;

    /**
     * Logable method calls
     *
     * @var array
     */
    protected $logable = [
        'fetchOne',
        'fetchCol',
        'fetchPairs',
        'fetchRow',
        'fetchAll',
        'fetchAssoc',
        'query',
        'execute'
    ];

    public function fetchOne()
    {
        return $this->callMethod('fetchOne', func_get_args());
    }

    public function fetchCol()
    {
        return $this->callMethod('fetchCol', func_get_args());
    }

    public function fetchPairs()
    {
        return $this->callMethod('fetchPairs', func_get_args());
    }

    public function fetchRow()
    {
        return $this->callMethod('fetchRow', func_get_args());
    }

    public function fetchAll()
    {
        return $this->callMethod('fetchAll', func_get_args());
    }

    public function fetchAssoc()
    {
        return $this->callMethod('fetchAssoc', func_get_args());
    }

    public function query()
    {
        return $this->callMethod('query', func_get_args());
    }

    public function execute()
    {
        return $this->callMethod('execute', func_get_args());
    }

    /**
     * Constructor: Set the decorated class as $this->instance
     *
     * @param $instance
     */
    public function setInstance($instance)
    {
        $this->instance = $instance;
    }

    /**
     * Will prepend "EXPLAIN" to a given SQL statement and format the result as a simple table
     *
     * @param $args
     * @return string
     * @throws \Exception If explain certain statements is not possible
     */
    public function explain($args)
    {
        $sql = 'EXPLAIN ' . $args[0];

        $rows = $this->instance->fetchAll($sql, $args);

        // Get the column headers and put them first
        $head = array_keys($rows[0]);
        array_unshift($rows, $head);

        // Remove associative keys
        foreach ($rows as &$row) {
            $row = array_values($row);
        }

        // Determine longest row
        $length = [];
        foreach ($rows as $r) {
            foreach ($r as $c => $column) {
                $length[$c] = strlen($column) > $length[$c] ? strlen($column) : $length[$c];
            }
        }

        // format the rows
        $result = [];
        foreach ($rows as &$row) {
            foreach ($row as $c => &$column) {
                $column = sprintf("%-{$length[$c]}s", $column);
            }
            $result[] = implode(" | ", $row);
        }

        // Concatenate the rows with newline chars
        return implode("\r\n", $result);
    }

    /**
     * Main wrapper method which decorated the actual query with some debug output
     *
     * @param $method
     * @param $args
     * @return mixed
     */
    public function callMethod($method, $args)
    {
        // Print header
        if (in_array($method, $this->logable)) {
            $this->printBeginBlock($args);
        }

        // Run the actual query and measure the time
        $start = microtime();
        $result = call_user_func_array([$this->instance, $method], $args);
        $duration = microtime() - $start;

        // Print footer (explain, duration, separator)
        if (in_array($method, $this->logable)) {
            $this->printEndBlock($args, $result, $duration);
        }

        return $result;
    }

    /**
     * Allow GET access to the wrapped instance
     *
     * @param $key
     * @return mixed
     */
    public function __get($key)
    {
        return $this->instance->$key;
    }

    /**
     * Allow SET access to the wrapped instance
     *
     * @param $key
     * @param $value
     * @return mixed
     */
    public function __set($key, $value)
    {
        return $this->instance->$key = $value;
    }

    /**
     * Simple logger which writes all queries to the file system
     *
     * @param $data
     * @param $suffix
     */
    public function debug($data, $suffix = null)
    {
        $base = Shopware()->DocPath('media_' . 'temp');
        $path = $base . 'migration';
        if ($suffix) {
            $path .= '_' . $suffix;
        }
        $path .= '.log';


        error_log(print_r($data, true) . "\r\n", '3', $path);
    }

    /**
     * Helper to print a "begin" block to the logfile
     *
     * @param $args
     * @return string
     */
    public function printBeginBlock($args)
    {
        $callers = debug_backtrace();
        $caller = array_map(
            function ($arr) {
                return $arr['function'];
            },
            array_reverse(array_slice($callers, 2, 5))
        );
        $caller = implode('=>', $caller);

        $begin_line = '>>> ' . $caller;
        $this->debug($begin_line);
        $this->debug($args[0]);
    }

    /**
     * Helper to print a "end" block to the logfile
     *
     * @param $args
     * @param $result
     * @param $duration
     */
    public function printEndBlock($args, $result, $duration)
    {
        $rows = 'Unknown';
        if (method_exists($result, 'rowCount')) {
            $rows = $result->rowCount();
        }

        try {
            $explained = $this->explain($args);
        } catch (Exception $e) {
            $explained = "";
        }


        try {
            $this->debug("\r\nExplain:\r\n" . print_r($explained, true) . "\r\n");
        } catch (Exception $e) {
            // Query is not explainable
        }

        $this->debug("Duration: " . $duration);
        $this->debug("RowCount: " . $rows);
        $this->debug("\r\n\r\n");
    }
}
