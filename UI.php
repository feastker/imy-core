<?php
/**
 * Created by PhpStorm.
 * User: rkrishtun
 * Date: 24.08.18
 * Time: 9:43
 */

namespace Imy\Core;

use Imy\Core\Tools;


class UI
{

    static function show($template, $opts = [])
    {
        $uifolder = VIEW . (defined(
                'PROJECT_TEMPLATE_DIRECTORY'
            ) ? PROJECT_TEMPLATE_DIRECTORY . DS : '') . 'ui' . DS . $template . DS;

        if (!is_dir($uifolder)) {
            throw new \Exception('Нет папки шаблона ' . $uifolder);
        }

        $part = !empty($opts['part']) ? $opts['part'] : 'init';

        $filename = $uifolder . $part . '.php';

        if (!file_exists($filename)) {
            throw new \Exception('Нет файла шаблона ' . $filename);
        }

        $ui_hash = md5(uniqid());

        $content = Tools::get_include_contents(
            $filename,
            (isset($opts['vars']) ? $opts['vars'] : $opts) + ['ui_hash' => $ui_hash]
        );

        return $content;
    }
}
