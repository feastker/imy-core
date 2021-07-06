<?php

namespace Imy\Core\Sql;

use Imy\Core\DB;
use Imy\Core\Exception;

abstract class Query
{

    /**
     * @abstract
     * @return string
     */
    abstract public function toString();

    /**
     * @var string[]
     */
    protected $tables = array();

    /**
     * @var Database
     */
    protected $connection;

    /**
     * @var string
     */
    protected $connection_name = 'default';

    /**
     * @param string|null|Database $database
     * @return \DBSelect
     * @throws Exception_System
     */
    public function setDatabase($database)
    {
        if ($database === null) {
            $this->setConnection(DB::getInstance());
        } else {
            if (is_string($database)) {
                $this->setConnection(DB::getInstance($database));
            } else {
                if ($database instanceof DB) {
                    $this->setConnection($database);
                } else {
                    throw new Exception\System('Соединение не указано');
                }
            }
        }

        return $this;
    }

    /**
     * @param Database $connection
     */
    protected function setConnection(DB $connection)
    {
        $this->connection = $connection;
    }

    /**
     * @return Database
     */
    public function getConnection()
    {
        return $this->connection;
    }

    /**
     * @return PDOStatement
     * @throws Exception_System
     */
    public function execute($die = false)
    {
        if ($die) {
            die($this->toString());
        }
        return $this->connection->query($this->toString());
    }
}
