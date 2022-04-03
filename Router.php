<?php

namespace Imy\Core;

use Imy\Core\Config;

class Router
{
    static $project;
    static $rules = [];
    static $uri;
    static $uparts;
    static $route;
    static $alias;
    static $folder;
    static $params;
    static $routing;
    static $sub;
    static $url;

    static function init()
    {
        self::$project = basename(getCWD());


        define('ROOT', str_replace(DS . DS, DS, str_replace(DS . 'cron', DS, getCWD() . DS)));

        $root_folder = ROOT;

        if (!empty(Config::get('system.app'))) {
            $root_folder = ROOT . '../' . Config::get('system.app');
        }

        define('APP', $root_folder);
        define('VIEW', $root_folder . 'view' . DS);
        define('PUB', ROOT . 'public' . DS);
        define('LIBS', ROOT . 'libs' . DS);
        define('UP', ROOT . 'uploads' . DS);

        if (!empty(Config::get('system.app'))) {
            $router_path = $root_folder . 'router.php';
            if (file_exists($root_folder . 'enum.php')) {
                include $root_folder . 'enum.php';
            }
        }

        if (!empty($_SERVER['HTTP_HOST']) && empty($router_path)) {
            $tmp_domain = explode('.', $_SERVER['HTTP_HOST']);
            self::$sub = $tmp_domain[0];

            $tmp_domain = implode('_', $tmp_domain);
            define('SITE', $_SERVER['HTTP_HOST']);
            $router_path = ROOT . 'router_' . $tmp_domain . '.php';
        }

        if (!empty($router_path) && !file_exists($router_path)) {
            $router_path = ROOT . 'router.php';
        }


        if (!empty($router_path) && file_exists($router_path)) {
            self::$rules = include $router_path;
        }

        $router_folder = $root_folder . 'router' . DS;
        if(file_exists($router_folder)) {
            $router_files = array_diff(scandir($router_folder), array('.', '..'));
            foreach($router_files as $file) {
                self::$rules[str_replace('.php','',$file)] = include $router_folder . $file;
            }
        }

        self::$routing = Config::get('system.routing');

        @self::$uri = $_SERVER['REQUEST_URI'];

        if (!empty($_SERVER)) {
            self::$url = @((!empty(@$_SERVER['HTTPS'])) ? 'https' : 'http') . '://' . @$_SERVER['HTTP_HOST'] . @$_SERVER['REQUEST_URI'];
        }

        if (self::$routing == 'index') {
            $tmp = explode('?', self::$uri);
            if (!empty($tmp[1])) {
                self::$params = $tmp[1];
            }
        } else {
            self::$uparts = explode('/', self::$uri);
            $tmp = explode('?', self::$uri);
            self::$uri = $tmp[0];
            if (!empty($tmp[1])) {
                self::$params = $tmp[1];
            }

            $last = self::$uparts[count(self::$uparts) - 1];
            if (strpos($last, '.html')) {
                $tmp = explode('?', $last);
                $tmp = explode('#', $tmp[0]);
                self::$alias = str_replace('.html', '', $tmp[0]);
            }
        }

        Lang::init();

        foreach (self::$rules as $k => $v) {
            if (self::$routing == 'index') {
                if (self::$uri == '/' || self::$uri == Config::get('system.index') || empty(self::$params)) {
                    self::$route = 'Main';
                    break;
                }

                if (strpos(self::$uri, $k) !== false) {
                    self::$route = $v;
                    break;
                }
            } else {
                if (preg_match('/^\/' . $k . '/ui', self::$uri)) {
                    self::$route = $v;
                    break;
                }
            }
        }

        if(self::$route == 'Page404') {
            $uri = explode('/',self::$uri);
            array_pop($uri);
            array_shift($uri);
            if(count($uri) == 2) {
                if(!empty(self::$rules[$uri[0]])) {
                    self::$folder = $uri[0];
                    if(!empty(self::$rules[$uri[0]][$uri[1]])) {
                        self::$route = self::$rules[$uri[0]][$uri[1]];
                    }
                }
            }
        }
    }
}
