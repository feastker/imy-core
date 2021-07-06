<?php
/**
 * Created by PhpStorm.
 * User: Feast
 * Date: 25.08.2018
 * Time: 12:06
 */

namespace Imy\Core;

class Config
{

    static $data = [];

    static function release($arr, $name = 'default')
    {
        self::$data[$name] = $arr;

        return self::$data;
    }

    static function get($str, $name = 'default')
    {
        $str = explode('.', $str);
        $data = !empty(self::$data[$name]) ? self::$data[$name] : false;


        foreach ($str as $part) {
            if (!empty($data[$part])) {
                $data = $data[$part];
            } else {
                $data = false;
            }
        }

        return $data;
    }
}
