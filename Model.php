<?php

namespace Imy\Core;

class Model
{

    protected $info;
    protected $changed_fields;
    protected $table;
    protected $database;

    public function __construct($table = false, $database = null)
    {
        $this->setTable($table);
        $this->setDatabase($database);
    }

    public function setTable($name)
    {
        $this->table = $name;
    }

    public function setDatabase($name)
    {
        $this->database = $name;
    }

    public function getDatabase()
    {
        return $this->database;
    }

    public function factory()
    {
        $this->info = [];
        $this->changed_fields = [];

        return $this;
    }

    public function get()
    {
        return DBSelect::factory($this->table, $this->database)
            ->setResultType(DBSelect::RESULT_TYPE_CLASS, get_called_class());
    }

    public function copy($add)
    {
        $fields = get_object_vars($this);
        $to_unset = ['id', 'info', 'changed_fields', 'table', 'database'];
        foreach ($to_unset as $field) {
            unset($fields[$field]);
        }

        $fields = array_keys($fields);

        $item = M($this->table)->factory();
        foreach ($fields as $field) {
            $item->setValue($field, $this->{$field});
        }

        foreach ($add as $field => $value) {
            $item->setValue($field, $value);
        }

        $id = $item->save();

        return $id;
    }

    public function getByFields(array $fields)
    {
        if (!$fields) {
            return false;
        }

        $dbs = static::get();

        foreach ($fields as $k => $v) {
            $dbs->where($k, $v);
        }

        return $dbs->limit(1)->fetch();
    }

    public function getId()
    {
        //$key = 'id_' . str_replace('_','',$this->table);
        $key = 'id';

        return $this->{$key};
    }

    public function getById($id)
    {
        return $this->get()->where('id', $id)->fetch();
    }

    public function setValues($row)
    {
        foreach ($row as $field => $value) {
            if (!isset($this->info[$field]) || $this->info[$field] !== $value) {
                $this->changed_fields[$field] = $value;
            }
        }

        return $this;
    }

    public function setValue($key, $value)
    {
        if ((!empty($this->info[$key]) && $this->info[$key] != $value) || empty($this->info[$key])) {
            $this->changed_fields[$key] = $value;
        }

        return $this;
    }

    public function save($nomodify = false, $die = false)
    {
        $query = $this->getDBManager();
        $pk = $this->getPrimary();

        if ($this->isNew()) {
            if (count($this->changed_fields)) {
                foreach ($this->changed_fields as $key => $value) {
                    $query->set($key, $value);
                }

//                $query->set('creator', User::$info->id_user);
//                $query->set('cdate', date('Y-m-d H:i:s'));

                $result = $query->insert($die);

                if ($result->rowsAffected()) {
                    $this->saveSuccess();
                    $this->$pk = $result->lastId();

                    return $result->lastId();
                }
            }
        } else {
            if (empty($this->changed_fields)) {
                return true;
            }

            $query = $this->getDBManager();
            foreach ($this->changed_fields as $key => $value) {
                $query->set($key, $value);
            }

            if (!$nomodify) {
//                $query->set('modifier', User::$info->id_user);
//                $query->set('mdate', date('Y-m-d H:i:s'));
            }
            $result = $query->where($pk, $this->$pk)->update($die);

            $this->saveSuccess();

            return $result;
        }

        $this->changed_fields = [];
        $this->increase_fields = [];

        return false;
    }

    public function delete($show = false)
    {
        $pk = $this->getPrimary();

        try {
            $result = $this->getDBManager()
                ->where($pk, $this->$pk)
                ->delete($show);

            return true;
        } catch (Exception\Database $e) {
            $this->last_error = $e->getMessage();
        }

        return false;
    }

    protected function isNew()
    {
        $id = $this->getPrimary();

        return empty($this->$id);
    }

    protected function getPrimary()
    {
        $id = 'id';

        return $id;
    }

    protected function saveSuccess()
    {
        foreach ($this->changed_fields as $key => $value) {
            $this->$key = $value;
        }
        $this->changed_fields = [];
    }

    protected function getDBManager()
    {
        return DBManager::factory($this->table, $this->database);
    }

    public function getTable()
    {
        return $this->table;
    }

}
