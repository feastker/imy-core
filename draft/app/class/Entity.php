<?php

use Imy\Core\User;

abstract class Entity
{

    protected $info;
    protected $model;
    protected $database = 'default';

    function __construct($data = false)
    {
        $this->model = M($this->entity,$this->database);

        if (!empty($data)) {
            if (is_numeric($data)) {
                $this->getById($data);
            } elseif (is_object($data)) {
                $this->info = $data;
            } elseif (is_array($data)) {
                $this->getBy($data);
            }
        }

    }


    function get($key)
    {
        return $this->info->{$key};
    }

    function __get($key)
    {
        return isset($this->info->{$key}) ? $this->info->{$key} : false;
    }

    function create($data)
    {
        $this->info = M($this->entity,$this->database)->factory();
        return $this->set($data);
    }

    function set($key, $val = false)
    {
        if (is_array($key)) {
            $this->info->setValues($key);
        } else {
            $this->info->setValue($key, $val);
        }

        return $this->info->save();
    }

    function getBy($field, $value = false)
    {

        $where = [];
        if (is_array($field)) {
            foreach ($field as $k => $v) {
                $where[$k] = $v;
            }
        } else {
            $where[$field] = $value;
        }

        $this->info = M($this->entity,$this->database)->get();
        foreach ($where as $k => $v) {
            if (is_array($v)) {
                $this->info = $this->info->where($k, $v[0], $v[1]);
            } else {
                $this->info = $this->info->where($k, $v);
            }
        }

        $this->info = $this->info->fetch();

    }

    function getMany($field, $value = false, $order = false,$dir = 'ASC')
    {

        $where = [];
        if (is_array($field)) {
            foreach ($field as $k => $v) {
                $where[$k] = $v;
            }
        } else {
            $where[$field] = $value;
        }


        $result = $this->model->get();
        foreach ($where as $k => $v) {
            $result = $result->where($k, $v);

        }

        if(!empty($order))
            $result = $result->orderBy($order, $dir);

        $result = $result->fetchAll();

        $class = get_class($this);

        $objects = [];
        foreach ($result as $item) {
            $objects[] = new $class($item);
        }

        return $objects;
    }

    function getById($id)
    {
        $this->getBy('id', $id);
    }

    function delete() {
        $this->info->delete();
    }

    function getInfo()
    {
        return $this->info;
    }

    function exist()
    {
        return !empty($this->info) ? true : false;
    }

    function error($msg)
    {
        return [
            'status'  => 'error',
            'message' => $msg
        ];
    }

    function success($msg)
    {
        return [
            'status'  => 'success',
            'message' => $msg
        ];
    }

    function reset()
    {
        $this->info = false;
    }
}
