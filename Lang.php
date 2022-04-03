<?php
namespace Imy\Core;

class Lang {

    static $localization;
    static $guide;
    static $default_lang = 'ru';

    static function init() {

        $tmp = explode('/', Router::$uri);
        $lang = @$tmp[1];

        $file = self::check_language($lang);
        if(!empty($file)) {
            self::$localization = $lang;
            self::$guide = include $file;

            unset($tmp[1]);

            if($lang != self::$default_lang)
                Router::$uri = implode('/', $tmp);
        }
        else {
            $file = self::check_language(self::$default_lang);
            if(!empty($file) && self::$default_lang != $lang) {
                throw new Exception\Redirect('/' . self::$default_lang . '/' . substr(Router::$uri,1));
            }
        }
    }

    static function check_language($lang) {

        $file = APP . 'lang' . DS . $lang . '.php';

        if(file_exists($file)) {
            return $file;
        }
        else {
            return false;
        }
    }

    static function get($name) {
        $str = explode('.', $name);
        $data = self::$guide;

        foreach($str as $part) {
            $data = $data[$part];
        }

        return $data;
    }
}
