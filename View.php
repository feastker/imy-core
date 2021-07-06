<?php
/**
 * Created by PhpStorm.
 * User: Feast
 * Date: 18.08.2018
 * Time: 13:03
 */

namespace Imy\Core;


class View
{

    static $global_data;

    static function render($template, $data, $full_path = false)
    {
        if (empty($template)) {
            die('Отсутствует шаблон для рендера');
        }

        extract($data, EXTR_SKIP);

        if (static::$global_data) {
            extract(static::$global_data, EXTR_SKIP | EXTR_REFS);
        }

        ob_start();

        $path = $full_path ? $template : VIEW . $template;

        try {
            if (file_exists($path . '.php')) {
                include $path . '.php';
            } else {
                include $path . '.html';
            }
        } catch (\Exception $e) {
            ob_end_clean();
        }

        return ob_get_clean();
    }

    static function setGlobal($key, $value)
    {
        self::$global_data[$key] = $value;
    }
}
