<?php

namespace Imy\Core;

use Imy\Core\Sql\Conditions;

class DBManager extends Conditions
{
    const QUERY_UPDATE = 1;
    const QUERY_DELETE = 2;
    const QUERY_INSERT = 3;
    const QUERY_REPLACE = 4;


    protected $ignore_insert           = false;
    protected $on_duplicate_key_update = false;

    protected $query_type          = 0;
    protected $values              = array();
    protected $values_on_duplicate = array();

    /**
     * @static
     * @throw Exception_System
     * @param string|array $tables
     * @param string|PDO $database
     *
     * @return DBManager
     */
    public static function factory($tables = null, $database = null)
    {
        return new self($tables, $database);
    }

    /**
     * @return DBManager
     */
    public function copy()
    {
        return clone $this;
    }

    /**
     * @throw Exception_System
     * @param string|array $tables
     * @param string|PDO $database
     */
    public function __construct($tables = null, $database = null)
    {
        if ($tables) {
            $this->table($tables);
        }

        $this->setDatabase($database);
    }

    /**
     * @param $tables
     * @return DBManager
     */
    public function table($tables)
    {
        $tables = (array)$tables;

        foreach ($tables as $table) {
            $this->tables[] = $table;
        }

        return $this;
    }

    /**
     * @param $state
     * @return DBManager
     */
    public function ignoreInsert($state)
    {
        $this->ignore_insert = (bool)$state;

        return $this;
    }

    /**
     * @param bool $state
     * @return DBManager
     */
    public function onDuplicateUpdate($state = true)
    {
        $this->on_duplicate_key_update = (bool)$state;

        return $this;
    }

    /**
     * @param string $key
     * @param int $value
     * @return DBManager
     */
    public function increment($key, $value = 1)
    {
        return $this->set($key, sprintf("%s + %d", $this->attachTable($key), $value), false);
    }

    /**
     * @param string $key
     * @param int $value
     * @param bool $safe
     * @return DBManager
     */
    public function decrement($key, $value = 1, $safe = false)
    {
        $key_wt = $this->attachTable($key);

        return $this->set(
            $key,
            $safe ? sprintf("IF (%s, %s - %d, 0)", $key_wt, $key_wt, $value) : sprintf(
                "%s - %d",
                $key_wt,
                $value
            ),
            false
        );
    }

    /**
     * @param string $key
     * @param string $value
     * @param bool $quote
     * @return DBManager
     */
    public function set($key, $value, $quote = true)
    {
        $key = $this->attachTable($key);

        if ($quote === true) {
            $value = $this->connection->quote($value);
        }

        if ($this->on_duplicate_key_update) {
            $this->values_on_duplicate[$key] = $value;
        } else {
            $this->values[$key] = $value;
        }

        return $this;
    }

    /**
     * @param array $values
     * @return DBManager
     */
    public function setFromArray(array $values)
    {
        foreach ($values as $key => $value) {
            $this->set($key, $value);
        }

        return $this;
    }

    /**
     * @return int
     */
    public function update($die = false)
    {
        return $this
            ->setQueryType(self::QUERY_UPDATE)
            ->execute($die);
    }


    public function insert($die = false)
    {
        $rows_affected = $this
            ->setQueryType(self::QUERY_INSERT)
            ->execute($die);

        return new DBResult($rows_affected, $this->connection->lastInsertId());
    }


    public function replace()
    {
        $rows_affected = $this
            ->setQueryType(self::QUERY_REPLACE)
            ->execute();

        return new DBResult($rows_affected, $this->connection->lastInsertId());
    }

    /**
     * @return int
     */
    public function delete($show = false)
    {
        return $this
            ->setQueryType(self::QUERY_DELETE)
            ->execute($show);
    }

    /**
     * @param $type
     * @return DBManager
     */
    public function setQueryType($type)
    {
        $this->query_type = $type;

        return $this;
    }

    /**
     * Execute
     *
     * @return int
     */
    public function execute($die = false)
    {
        if ($die) {
            die($this->toString());
        }
        return $this->connection->exec($this->toString());
    }

    /**
     * @param $values
     * @return string
     */
    protected function getValuesAsString($values)
    {
        $sql = '';
        $second = false;

        foreach ($values as $key => $value) {
            if ($second) {
                $sql .= ", ";
            }

            $sql .= "$key = $value";
            $second = true;
        }

        return $sql;
    }

    /**
     * @return string
     * @throws Exception
     */
    public function toString()
    {
        switch ($this->query_type) {
            default:
                throw new \Exception("query type not set");

            case self::QUERY_INSERT:
                if (empty($this->values)) {
                    throw new \Exception("No values to insert");
                }

                $sql = "INSERT " . ($this->ignore_insert ? " IGNORE " : "") .
                    "INTO `{$this->tables[0]}` SET " .
                    $this->getValuesAsString($this->values);

                if ($this->on_duplicate_key_update and $this->values_on_duplicate) {
                    $sql .= ' ON DUPLICATE KEY UPDATE ' .
                        $this->getValuesAsString($this->values_on_duplicate);
                }

                break;

            case self::QUERY_REPLACE:
                if (empty($this->values)) {
                    throw new \Exception("No values to insert");
                }

                $sql = "REPLACE INTO `{$this->tables[0]}` SET " . $this->getValuesAsString($this->values);

                break;

            case self::QUERY_UPDATE:
                if (empty($this->values)) {
                    throw new \Exception("No values to update");
                }

                $sql = "UPDATE `" . implode('`,`', $this->tables) . "` SET " .
                    $this->getValuesAsString($this->values) .
                    ($this->conditions ? " WHERE " . $this->compileConditions($this->conditions) : "") .
                    ($this->limit ? " LIMIT " . $this->limit : "");

                break;

            case self::QUERY_DELETE:
                $sql = "DELETE FROM " . implode(',', $this->tables) .
                    ($this->conditions ? " WHERE " . $this->compileConditions($this->conditions) : "");

                break;
        }

        return $sql;
    }

    public function transaction(Closure $cb)
    {
        $this->connection->getConnection()->beginTransaction();

        try {
            $result = call_user_func($cb, $this);

            $this->connection->getConnection()->commit();

            return $result;
        } catch (\Exception $e) {
            $this->connection->getConnection()->rollBack();

            throw new Exception\Database($e->getMessage());
        }
    }
}
