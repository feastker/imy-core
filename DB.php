<?php
/**
 * Created by PhpStorm.
 * User: Feast
 * Date: 25.08.2018
 * Time: 12:01
 */

namespace Imy\Core;


class DB
{

    private static $instances = array();
    protected      $connection_name;
    protected      $pdo;

    public static function getInstance($connection_name = "default")
    {
        if (!isset(self::$instances[$connection_name])) {
            self::$instances[$connection_name] = new static($connection_name);
        }

        return self::$instances[$connection_name];
    }

    public function __construct($connection_name)
    {
        $this->connection_name = $connection_name;

        if (!$config = Config::get("db.$connection_name")) {
            throw new Exception\Database("config for db: $connection_name not found");
        }

        $driver = $config['driver'] ?? 'mysql';
        

        $exclude_params = ['driver', 'user', 'password', 'charset', 'persistent', 'ca'];
        
        $dsn_params = '';

        foreach ($config as $key => $value) {
            if (in_array($key, $exclude_params)) {
                continue;
            }
            
            if ($dsn_params) {
                $dsn_params .= ';';
            }

            $dsn_params .= "{$key}={$value}";
        }
        
        try {
            $opts = [
                \PDO::ATTR_STRINGIFY_FETCHES  => false,
                \PDO::ATTR_EMULATE_PREPARES   => false,
                \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_PERSISTENT         => $config['persistent'] ?? false
            ];

            // MySQL/MariaDB специфичные настройки
            if (in_array($driver, ['mysql', 'mariadb'])) {
                $opts[\PDO::MYSQL_ATTR_INIT_COMMAND] = 'SET NAMES "' . ($config['charset'] ?? 'utf8') . '";';
                
                if(@$config['ca']) {
                    $opts[\PDO::MYSQL_ATTR_SSL_CA] = $config['ca'];
                    $opts[\PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = true;
                }
                else {
                    $opts[\PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
                }
            }

            $this->pdo = new \PDO(
                "{$driver}:{$dsn_params}", $config['user'], $config['password'], $opts
            );
        } catch (\Exception $e) {
            die($e->getMessage());
        }
    }

    public function lastInsertId()
    {
        return (int)$this->pdo->lastInsertId();
    }

    public function getConnectionName()
    {
        return $this->connection_name;
    }

    public function getConnection()
    {
        return $this->pdo;
    }

    public function beginTransaction()
    {
        $this->pdo->beginTransaction();

        return $this;
    }

    public function rollBack()
    {
        $this->pdo->rollBack();

        return $this;
    }

    public function commit()
    {
        $this->pdo->commit();

        return $this;
    }

    public function query($query, array $params = [])
    {
        try {
//            if (DEBUG) {
//                Profiler::start();
//            }

            // Params exists, use prepare function
            if ($params) {
                if (!$stmp = $this->pdo->prepare($query)) {
                    throw new Exception\Database("bad query: {$query}");
                }

                $stmp->execute($params);
            } // Immediately execute query, without prepare
            else {
                if (!$stmp = $this->pdo->query($query)) {
                    throw new Exception\Database("bad query: {$query}");
                }
            }

//            if (DEBUG) {
//                Profiler::log($query, 'mysql');
//            }
        } catch (\PDOException $e) {
            throw new Exception\Database("{$e->getMessage()}<br>query:{$query}");
        }

        return $stmp;
    }

    public function exec($query)
    {
//        if (DEBUG) {
//            Profiler::start();
//        }

        if (false === ($count = $this->pdo->exec($query))) {
            throw new Exception\Database("bad query: $query");
        }

//        if (DEBUG) {
//            Profiler::log($query);
//        }

        return $count;
    }

    public function quote($value)
    {
        if ($value === null) {
            return 'NULL';
        } elseif ($value === true) {
            return "'1'";
        } elseif ($value === false) {
            return "'0'";
        } elseif (is_object($value)) {
//            if ($value instanceof DBSelect_Expression) {
//                return (string)$value;
//            }
//
//            if ($value instanceof DBSelect) {
//                return "({$value})";
//            }

            return $this->quote((string)$value);
        } elseif (is_array($value)) {
            return '(' . implode(', ', array_map(array($this, __FUNCTION__), $value)) . ')';
        } elseif (is_float($value)) {
            return $this->quote(sprintf('%F', $value));
        }

        return $this->pdo->quote($value);
    }

    public function refreshConnect()
    {
        $this->query("SELECT 1");

        return true;
    }

}
