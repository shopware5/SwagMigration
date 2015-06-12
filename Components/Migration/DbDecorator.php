<?php
/**
 * Shopware 4.0
 * Copyright © 2013 shopware AG
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
 * Simple decorator for the source database: Will log certain queries to media/temp/migration.log
 *
 * Class Shopware_Components_Migration_DbDecorator
 *
 * @category Shopware
 * @package Shopware\Plugins\SwagMigration\Components\Migration
 * @copyright Copyright (c) 2012, shopware AG (http://www.shopware.de)
 */
class Shopware_Components_Migration_DbDecorator
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
    protected $logable = array(
        'fetchOne',
        'fetchCol',
        'fetchPairs',
        'fetchRow',
        'fetchAll',
        'fetchAssoc',
        'query',
        'execute'
    );

    /**
     * Constructor: Set the decorated class as $this->instance
     *
     * @param $instance
     */
    public function __construct($instance)
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
        $length = array();
        foreach ($rows as $r) {
            foreach ($r as $c => $column) {
                $length[$c] = strlen($column) > $length[$c] ? strlen($column) : $length[$c];
            }
        }

        // format the rows
        $result = array();
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
    public function __call($method, $args)
    {
        // Print header
        if (in_array($method, $this->logable)) {
            $this->printBeginBlock($args);
        }

        // Run the actual query and measure the time
        $start = microtime();
        $result = call_user_func_array(array($this->instance, $method), $args);
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
