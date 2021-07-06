<?php
/**
 * Created by PhpStorm.
 * User: Feast
 * Date: 26.08.2018
 * Time: 14:31
 */

namespace Imy\Core;


class DBResult
{
    protected $affected_rows;
    protected $insert_id;

    public function __construct($affected_rows, $insert_id)
    {
        $this->affected_rows = $affected_rows;
        $this->insert_id = $insert_id;
    }

    public function rowsAffected()
    {
        return $this->affected_rows;
    }

    public function lastId()
    {
        return $this->insert_id;
    }
}
