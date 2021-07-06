<?php
/**
 * Created by PhpStorm.
 * User: Feast
 * Date: 18.08.2018
 * Time: 12:08
 */

namespace Imy\Core;


class Definer
{

    static $defines;

    static function init()
    {
        self::define_system();


        foreach (self::$defines as $define_arr) {
            foreach ($define_arr as $k => $v) {
                if (!defined($k)) {
                    define($k, $v);
                }
            }
        }
    }

    static function define_system()
    {
        $root = explode(DIRECTORY_SEPARATOR, dirname(__FILE__));
        array_pop($root);
        $root = implode(DIRECTORY_SEPARATOR, $root);

        self::$defines['system'] = [
            'DS'        => DIRECTORY_SEPARATOR,
            'NOW'       => date('Y-m-d H:i:s'),
            'NOW_DT'    => date('Y-m-d'),
            'CORE_ROOT' => $root . DIRECTORY_SEPARATOR,
            'CORE_LIBS' => $root . DIRECTORY_SEPARATOR . 'core' . DIRECTORY_SEPARATOR . 'libs' . DIRECTORY_SEPARATOR,
        ];
    }

//    static function define_common() {
//
//        self::$defines['common'] =  [
//            'DS'        => DIRECTORY_SEPARATOR,
//            'NOW'       => date('Y-m-d H:i:s'),
//            'CORE_ROOT' => $root . DIRECTORY_SEPARATOR
//        ];
//    }
}
