<?php

namespace Imy\Core;

use Imy\Core\Sql\Conditions;

class DBSelect extends Conditions
{
    const COLUMN_COMPARE = '<=>';

    const RESULT_TYPE_ASSOC = 1;
    const RESULT_TYPE_CLASS = 2;
    const RESULT_TYPE_COLUMN = 3;

    /**
     * @var string[]
     */
    protected $indexes = [];

    /**
     * @var array
     */
    protected $columns = [];

    /**
     * @var array
     */
    protected $groups = [];

    /**
     * @var array
     */
    protected $havings = [];

    /**
     * @var array
     */
    protected $joins = [];

    /**
     * @var int
     */
    protected $last_join_index;

    /**
     * @var string
     */
    protected $result_type = self::RESULT_TYPE_ASSOC;

    /**
     * @var string
     */
    protected $result_opt;

    /**
     * @var string
     */
    public $last_table;

    /**
     * @static
     * @throw Exception_System
     * @param string|array $table_name
     * @param string|Database $database
     *
     * @return DBSelect
     */

    protected $last_database;

    public static function factory($table_name = null, $database = null)
    {
        return new self($table_name, $database);
    }

    /**
     * @throw Exception_System
     * @param string|array $table_name
     * @param string|Database $database
     */
    public function __construct($table_name = null, $database = null)
    {
        if (!empty($database)) {
            $this->last_database = $database;
        }

        if ($table_name) {
            $this->from($table_name);
        }

        $this->setDatabase($database);
    }

    /**
     * @param string|array $column
     *
     * @return DBSelect
     */
    public function select($column)
    {
        if (is_array($column)) {
            foreach ($column as $c) {
                $this->columns[] = $c;
            }
        } else {
            for ($i = 0; $i < func_num_args(); $i++) {
                $this->columns[] = func_get_arg($i);
            }
        }

        return $this;
    }

    /**
     * @param string|array $table_name
     * @return DBSelect
     */
    public function from($table_name)
    {
        if (func_num_args() >= 2) {
            foreach (func_get_args() as $t) {
                $this->from($t);
            }

            return $this;
        }

        // Add new table
        $this->tables[] = $table_name;

        // Save last added table for index forcing
        $this->last_table = $table_name;

        return $this;
    }

    /**
     * @param string $table_name
     * @param string $type
     *
     * @return DBSelect
     */
    public function join($table_name, $type = "")
    {
        $index = count($this->joins);

        $this->joins[$index] = [$table_name, $type, []];
        $this->last_join_index = $index;

        return $this;
    }

    /**
     * @param string $left_column_name
     * @param string $value_or_right_column_name
     * @param string $op
     *
     * @return DBSelect
     */
    public function on($left_column_name, $value_or_right_column_name, $op = self::COLUMN_COMPARE, $type = 'AND')
    {
        if ($this->last_join_index !== null) {
            $this->joins[$this->last_join_index][2][] = [
                $type => [
                    $left_column_name,
                    $op,
                    $value_or_right_column_name
                ]
            ];
        }

        return $this;
    }

    /**
     * @return string
     */
    protected function compileJoins()
    {
        $string = '';

        foreach ($this->joins as $join) {
            list($table_name, $type, $conditions) = $join;

            if (strtolower($type) != 'straight') {
                $string .= " {$type} JOIN ";
            } else {
                $string .= " STRAIGHT_JOIN ";
            }
            $string .= "{$table_name} ON (" . $this->compileConditions($conditions) . ")";
        }

        return $string;
    }

    /**
     * @param             $column
     * @param string|null $sort
     * @param $attach_table bool
     * @return DBSelect
     */
    public function groupBy($column, $sort = null, $attach_table = false)
    {
        $this->groups[] = ($attach_table ? $this->attachTable($column) : $column) . ($sort !== null and in_array(
                    $sort,
                    self::$available_orders
                ) ? " {$sort}" : '');

        return $this;
    }

    /**
     * @param string $column
     * @param string $value
     * @param string $op
     * @return DBSelect
     */
    public function having($column, $value, $op = '=')
    {
        $this->havings[] = array('AND' => array($column, $op, $value));

        return $this;
    }

    /**
     * @param string $column
     * @param string $value
     * @param string $op
     * @return DBSelect
     */
    public function orHaving($column, $value, $op = '=')
    {
        $this->havings[] = array('OR' => array($column, $op, $value));

        return $this;
    }

    /**
     * @param $index_name
     * @return DBSelect
     */
    public function setIndex($index_name)
    {
        $this->indexes[$this->last_table] = $index_name;

        return $this;
    }

    /**
     * @return string
     */
    public function toString()
    {
        $columns = $this->columns;

        if (!$columns) {
            $columns[] = '*';
        }

        $sql = 'SELECT ' . implode(', ', $columns) . ' FROM ';

        foreach ($this->tables as $i => $table) {
            $table = (string)$table;

            if ($i > 0) {
                $sql .= ', ';
            }

            $sql .= strpos($table, '.') === false && strpos($table, ' ') === false ? '`' . $table . '`' : $table;

            if (isset($this->indexes[$table])) {
                $sql .= ' USE INDEX (' . $this->indexes[$table] . ')';
            }
        }

        if ($this->joins) {
            $sql .= $this->compileJoins();
        }

        if ($this->conditions) {
            $sql .= ' WHERE ' . $this->compileConditions($this->conditions);
        }

        if ($this->groups) {
            $sql .= ' GROUP BY ' . implode(', ', $this->groups);
        }

        if ($this->havings) {
            $sql .= ' HAVING ' . $this->compileConditions($this->havings);
        }

        if ($this->orders) {
            $sql .= ' ORDER BY ' . implode(', ', $this->orders);
        }

        if ($this->limit !== null) {
            $sql .= ' LIMIT ' . $this->limit . ' ';

            if ($this->offset !== null) {
                $sql .= ' OFFSET ' . $this->offset;
            }
        }

        return $sql;
    }

    /**
     * Get table by index
     *
     * @param int $index
     * @return DBSelect_Table
     */
    public function getTable($index = 0)
    {
        return $this->tables[$index];
    }

    /**
     * @param int $type
     * @param string $class_name
     *
     * @return $this
     */
    public function setResultType($type, $class_name = null)
    {
        $this->result_type = $type;
        $this->result_opt = $class_name;

        return $this;
    }

    /**
     * Получить ассоциативный массив
     * @return array
     */
    public function fetchAssoc()
    {
        return $this->setResultType(self::RESULT_TYPE_ASSOC)
            ->fetch();
    }

    /**
     * Получить ассоциативный массив
     * @param bool $indexed_array
     * @return array
     */
    public function fetchAssocAll($indexed_array = false)
    {
        return $this->setResultType(self::RESULT_TYPE_ASSOC)
            ->fetchAll(false, $indexed_array);
    }

    /**
     * Получить 1 колонку
     * @param int $column_num
     * @return string
     */
    public function fetchColumn($column_num = 0)
    {
        return $this->setResultType(self::RESULT_TYPE_COLUMN, $column_num)
            ->fetch();
    }

    /**
     * Получить 1 колонку
     * @param int $column_num
     * @param bool $indexed_array
     * @return string[]
     */
    public function fetchColumns($column_num = 0, $indexed_array = false)
    {
        return $this->setResultType(self::RESULT_TYPE_COLUMN, $column_num)
            ->fetchAll(false, $indexed_array);
    }

    /**
     * Получить одну запись в виде объекта
     * @param string $class_name
     * @return mixed
     */
    public function fetchObject($class_name)
    {
        return $this->setResultType(self::RESULT_TYPE_CLASS, $class_name)
            ->fetch();
    }

    /**
     * Получить массив записей в виде объектов
     * @param string $class_name
     * @param bool|string $index_key
     * @return array
     */
    public function fetchObjects($class_name, $index_key = false)
    {
        return $this->setResultType(self::RESULT_TYPE_CLASS, $class_name)
            ->fetchAll(false, $index_key);
    }

    /**
     * @return array
     * @throws Exception_System
     */
    public function fetchUnique()
    {
        // BUG IN PDO
        //return $this->execute()
        //	->fetchAll(PDO::FETCH_COLUMN | PDO::FETCH_UNIQUE | PDO::FETCH_GROUP, (count($this->columns) == 1 ? 0 : 1));

        $values = [];

        $stmp = $this->execute();

        switch ($stmp->columnCount()) {
            case 2:
                while ($row = $stmp->fetch(\PDO::FETCH_NUM)) {
                    $key = array_shift($row);
                    $value = array_shift($row);

                    $values[$key] = $value;
                }

                break;

            case 1:
                while ($row = $stmp->fetchColumn()) {
                    $values[$row] = $row;
                }

                break;

            default:

                throw new Exception_System("DBSelect::fetchUnique - Wrong number of columns");
        }

        return $values;
    }

    /**
     * @return mixed
     */
    public function fetch($die = false)
    {
        $stmp = $this->execute($die);
        $result = false;

        switch ($this->result_type) {
            case self::RESULT_TYPE_ASSOC:
                $result = $stmp->fetch(\PDO::FETCH_ASSOC);
                break;

            case self::RESULT_TYPE_CLASS:
                $result = $stmp->fetchObject($this->result_opt);
                break;

            case self::RESULT_TYPE_COLUMN:
                $result = $stmp->fetchColumn($this->result_opt);
                break;
        }

        $stmp->closeCursor();
        if ($result && is_object($result)) {
            $result->setTable($this->last_table);
            $result->setDatabase($this->last_database);
        }
        return $result;
    }


    public function fetchAll($die = false, $indexed_array = true)
    {
        $results = [];
        $stmp = $this->execute($die);

        if ($indexed_array === false) {
            switch ($this->result_type) {
                case self::RESULT_TYPE_COLUMN:
                    return $stmp->fetchAll(\PDO::FETCH_COLUMN, $this->result_opt);

                case self::RESULT_TYPE_CLASS:
                    return $stmp->fetchAll(\PDO::FETCH_CLASS, $this->result_opt);

                case self::RESULT_TYPE_ASSOC:
                    return $stmp->fetchAll(\PDO::FETCH_ASSOC);
            }
        } else {
            if ($indexed_array === true) {
                switch ($this->result_type) {
                    // Can not be fetched with boolean index type
                    case self::RESULT_TYPE_CLASS:

                        $results = $stmp->fetchAll(\PDO::FETCH_CLASS, $this->result_opt);
                        foreach ($results as &$result) {
                            if ($result && is_object($result)) {
                                $result->setTable($this->last_table);
                                $result->setDatabase($this->last_database);
                            }
                        }

                        return $results;

                    case self::RESULT_TYPE_ASSOC:
                        while ($result = $stmp->fetch(\PDO::FETCH_ASSOC)) {
                            $results[current($result)] = $result;
                        }

                        $stmp->closeCursor();

                        return $results;

                    case self::RESULT_TYPE_COLUMN:
                        while ($result = $stmp->fetch(\PDO::FETCH_COLUMN, $this->result_opt)) {
                            $results[$result] = $result;
                        }

                        $stmp->closeCursor();

                        return $results;
                }
            } else {
                if (is_string($indexed_array)) {
                    switch ($this->result_type) {
                        case self::RESULT_TYPE_ASSOC:
                            while ($result = $stmp->fetch(\PDO::FETCH_ASSOC)) {
                                $results[$result[$indexed_array]] = $result;
                            }

                            $stmp->closeCursor();

                            return $results;

                        case self::RESULT_TYPE_CLASS:
                            while ($result = $stmp->fetchObject($this->result_opt)) {
                                $results[$result->$indexed_array] = $result;
                            }

                            $stmp->closeCursor();

                            return $results;
                    }
                }
            }
        }


        return $results;
    }

    public function copy()
    {
        return clone $this;
    }

    /**
     * Count rows by column
     *
     * @param $column
     * @return int
     */
    public function count($column = 'id')
    {
        // Чтобы не испортить текущие условия скопируем
        $copy = $this->copy();

        $copy->columns = ["COUNT({$column})"];
        $copy->orders = []; // При COUNT сортировки не нужны
        $copy->groups = []; // При COUNT группировки не нужны

        return (int)$copy->fetchColumn(0);
    }

    /**
     * Max value of column
     *
     * @param $column
     * @return int
     */
    public function max($column = 'id')
    {
        // Чтобы не испортить текущие условия скопируем
        $copy = $this->copy();

        $copy->columns = ["MAX({$column})"];
        $copy->orders = []; // При MAX сортировки не нужны

        return (int)$copy->fetchColumn(0);
    }

    public function getDBManager()
    {
        if (!isset($this->tables[0])) {
            throw new Exception\System("В DBSelect не указана таблица");
        }

        $manager = DBManager::factory($this->tables[0], $this->connection);

        // Copy all conditions
        $manager->conditions = $this->conditions;

        return $manager;
    }

    public function __toString()
    {
        return $this->toString();
    }


    /**
     * Очистить список SELECT колонок
     *
     * @return $this
     */
    public function clearSelect()
    {
        $this->columns = [];
        return $this;
    }

    /**
     * Очистить список GROUP BY
     *
     * @return $this
     */
    public function clearGroup()
    {
        $this->groups = [];
        return $this;
    }

    /**
     * Очистить список ORDER BY
     *
     * @return $this
     */
    public function clearOrder()
    {
        $this->orders = [];
        return $this;
    }


}
