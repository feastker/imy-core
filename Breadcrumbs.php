<?php

namespace Imy\Core;

class Breadcrumbs
{

    static $arr;

    static function add($name, $link = '')
    {
        $name = mb_strlen($name) > 60 ? iconv_substr($name, 0, 60, "UTF-8") . '...' : $name;

        self::$arr[] = [$name, $link];
    }

    static function get()
    {
        return self::$arr;
    }

    static function flush()
    {
        self::$arr = [];
    }

}
