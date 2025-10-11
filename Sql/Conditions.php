<?php

namespace Imy\Core\Sql;

use Imy\Core\DBSelect;

abstract class Conditions extends Query
{
    /**
     * @var array
     */
    protected $orders = array();

    /**
     * @var int
     */
    protected $limit;

    /**
     * @var int
     */
    protected $offset;

    /**
     * @var array
     */
    protected $conditions = array();

    /**
     * @var array
     */
    protected static $available_orders = array('ASC', 'DESC', 'NULL');

    /**
     * @param string $column
     * @param mixed $value
     * @param string $op
     * @return DBSelect|DBManager
     */
    public function where($column, $value, $op = '=')
    {
        return $this->andWhere($column, $value, $op);
    }

    /**
     * @param string $column
     * @param mixed $value
     * @param string $op
     * @return DBSelect|DBManager
     */
    public function andWhere($column, $value, $op = '=')
    {
        $this->conditions[] = array('AND' => array($column, $op, $value));

        return $this;
    }

    /**
     * @param string $column
     * @param mixed $value
     * @param string $op
     * @return DBSelect|DBManager
     */
    public function orWhere($column, $value, $op = '=')
    {
        $this->conditions[] = array('OR' => array($column, $op, $value));

        return $this;
    }

    /**
     * @return DBSelect|DBManager
     */
    public function whereOpen()
    {
        return $this->andWhereOpen();
    }

    /**
     * @return DBSelect|DBManager
     */
    public function andWhereOpen()
    {
        $this->conditions[] = array('AND' => '(');

        return $this;
    }

    /**
     * @return DBSelect|DBManager
     */
    public function orWhereOpen()
    {
        $this->conditions[] = array('OR' => '(');

        return $this;
    }

    /**
     * @return DBSelect|DBManager
     */
    public function whereClose()
    {
        return $this->andWhereClose();
    }

    /**
     * @return DBSelect|DBManager
     */
    public function andWhereClose()
    {
        $this->conditions[] = array('AND' => ')');

        return $this;
    }

    /**
     * @return DBSelect|DBManager
     */
    public function orWhereClose()
    {
        $this->conditions[] = array('OR' => ')');

        return $this;
    }

    /**
     * @param array $conditions
     * @return string
     */
    protected function compileConditions(array $conditions)
    {
        $last_condition = null;

        $sql = '';

        foreach ($conditions as $group) {
            foreach ($group as $logic => $condition) {
                if ($condition === '(') {
                    if (!empty($sql) and $last_condition !== '(') {
                        $sql .= ' ' . $logic . ' ';
                    }

                    $sql .= '(';
                } elseif ($condition === ')') {
                    $sql .= ')';
                } else {
                    if (!empty($sql) and $last_condition !== '(') {
                        $sql .= ' ' . $logic . ' ';
                    }

                    list($column, $op, $value) = $condition;

                    if ($value === null) {
                        if ($op === '=') {
                            $op = 'IS';
                        } elseif ($op === '!=') {
                            $op = 'IS NOT';
                        }
                    }

                    if (is_array($value) and $op === '=') {
                        $op = 'IN';
                    }

                    if (preg_match('~^nq(.+)$~', $op, $new_op)) {
                        $op = $new_op[1];
                    } else {
                        switch ($op) {
                            case 'BETWEEN':
                                if ($value[0] !== null && !strpos($value[0], '(')) {
                                    $value = $this->connection->quote($value[0]) . ' AND ' . $this->connection->quote(
                                            $value[1]
                                        );
                                } else {
                                    $value = $value[0] . ' AND ' . $value[1];
                                }
                                break;

                            // Special behavior for column comparing
                            case DBSelect::COLUMN_COMPARE:
                                $op = '=';
                                break;


                            default:
                                if (is_array($value) || ($value !== null && strpos($value, 'NOW()') === false)) {
                                    if (($op == 'IN' || $op == 'NOT IN') && is_array($value)) {
                                        $v = [];
                                        foreach ($value as $val) {
                                            if (!is_numeric($val) && $val !== null && strpos($val, '`') === false) {
                                                $val = $this->connection->quote($val);
                                            }
                                            $v[] = $val;
                                        }
                                        $value = '(' . implode(', ', $v) . ')';
                                    } else {
                                        if ($value !== null && strpos($value, '`') === false) {
                                            $value = $this->connection->quote($value);
                                        }
                                    }
                                }
                        }
                    }

                    $sql .= trim(
                        ($column !== null && !strpos($column, '(') && !strpos($column, '.') ? (strpos(
                            $column,
                            '`'
                        ) === false ? '`' . $column . '`' : $column) : $column) . ' ' . $op . ' ' . $value
                    );
                }

                $last_condition = $condition;
            }
        }

        $last_condition = null;

        return $sql;
    }

    /**
     * @param string $column
     * @param string $sort
     * @return DBSelect|DBManager
     */
    public function orderBy($column, $sort = 'ASC')
    {
        $this->orders[] = $column . (in_array($sort, self::$available_orders) ? " {$sort}" : '');

        return $this;
    }

    /**
     * @param int $limit
     * @return DBSelect|DBManager
     */
    public function limit($limit)
    {
        $limit = (int)$limit;

        $this->limit = ($limit > 0 ? $limit : null);

        return $this;
    }

    /**
     * @param int $offset
     * @return DBSelect|DBManager
     */
    public function offset($offset)
    {
        $offset = (int)$offset;

        $this->offset = ($offset > 0 ? $offset : null);

        return $this;
    }

    /**
     * @param int $page_number
     * @return DBSelect|DBManager
     */
    public function page($page_number)
    {
        if ($this->limit and $page_number >= 1) {
            return $this->offset(($page_number - 1) * $this->limit);
        }

        return $this;
    }

    /**
     * Wrap column with db table
     *
     * @param $column
     * @param $table
     * @return string
     */
    public function attachTable($column, $table = null)
    {
        if (is_int($table)) {
            $column = "{$this->tables[0]}.{$column}";
        } else {
            if (is_string($table)) {
                $column = "{$table}.{$column}";
            } else {
                $column = "{$this->tables[0]}.{$column}";
            }
        }

        return $column;
    }
}
