<?php

namespace Imy\Core;

class Core
{

    static $view = [];
    static $ajax = false;

    static function init($config_file = 'config')
    {
        header('Content-Type: text/html; charset=utf-8');

        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower(
                $_SERVER['HTTP_X_REQUESTED_WITH']
            ) == 'xmlhttprequest' || isset($_POST['is_ajax'])) {
            self::$ajax = true;
        }

        try {
            Definer::init();

            $config_file = getCWD() . DS . $config_file . '.php';
            if (!file_exists($config_file)) {
                $error = "\n" . 'There is no configuration file in ' . $config_file . "\n\n";
                $error .= "\n";
                die($error);
            }

            Config::release(include $config_file);

            $cache_config = Config::get('cache');
            if (!empty($cache_config)) {
                Cache::init();
            }

            Router::init();


            User::init();


            if (class_exists('CommonController')) {
                $common_controller = new \CommonController();

                if (method_exists($common_controller, 'first')) {
                    $common_controller->first();
                }

                if (!empty($_POST['common_handler']) && is_array($_POST['common_handler']) && !self::$ajax) {
                    $function = 'handler_' . array_keys($_POST['common_handler'])[0];

                    if (method_exists($common_controller, $function)) {
                        $common_controller->{$function}();
                    }
                } elseif (self::$ajax) {
                    if (method_exists($common_controller, 'ajax')) {
                        $common_controller->ajax();
                    }

                    if (!empty($_POST['action'])) {
                        $action = $_POST['action'];
                        $function = 'ajax_' . $action;
                        if (method_exists($common_controller, $function)) {
                            $common_controller->{$function}();
                        }
                    }
                }

                if (!self::$ajax) {
                    $common_controller->init();
                }

                self::$view = $common_controller->v;

                if (!empty($common_controller->t)) {
                    $template = $common_controller->t;
                }
            }

            $controller_name = ucfirst(Router::$route) . 'Controller';

            if(!empty(Router::$folder) && !class_exists($controller_name)) {
                $controller_file = APP . 'controller' . DS . Router::$folder . DS . $controller_name . '.php';
                if(file_exists($controller_file))
                    include $controller_file;
            }

            if (class_exists($controller_name)) {
                $controller = new $controller_name();
            } elseif (class_exists('Page404Controller')) {
                $controller = Page404Controller();
            } else {
                Tools::s(404);
            }

            if (method_exists($controller, 'first')) {
                $controller->first();
            }

            if (!empty($_POST['handler']) && is_array($_POST['handler']) && !self::$ajax) {
                $function = 'handler_' . array_keys($_POST['handler'])[0];

                if (method_exists($controller, $function)) {
                    $controller->{$function}();
                }
            } elseif (self::$ajax) {
                if (method_exists($controller, 'ajax')) {
                    $controller->ajax();
                }

                if (!empty($_POST['action'])) {
                    $action = $_POST['action'];
                    $function = 'ajax_' . $action;
                    if (method_exists($controller, $function)) {
                        $controller->{$function}();
                    }
                }
            }



            if (!self::$ajax) {
                $controller->init();
            }

            if (empty(self::$view)) {
                self::$view = [];
            }

            self::$view = array_merge(self::$view, $controller->v);
            if (!empty($controller->t)) {
                $template = $controller->t;
            }


            if (empty($template)) {
                if (self::$ajax) {
                    throw new Exception\Ajax;
                } else {
                    $template = (defined(
                            'PROJECT_TEMPLATE_DIRECTORY'
                        ) ? PROJECT_TEMPLATE_DIRECTORY . DS : '') . 'layout' . DS . 'default';
                }
            }

            self::$view['breadcrumbs'] = Breadcrumbs::get();

            throw new Exception\Stop;
        } catch (Exception\Stop $e) {
            echo View::render(
                $template,
                self::$view,
                !empty($controller->full_template_path) ? $controller->full_template_path : false
            );
        } catch (Exception\Refresh $e) {
            header("Refresh:0");
        } catch (Exception\Redirect $e) {
            header("HTTP/1.1 " . $e->getHTTPStatusCode() . " " . $e->getHTTPStatusText());
            header('Location: ' . $e->getRedirectURL());
            exit;
        } catch (Exception\NotFound $e) {
            header("HTTP/1.0 404 Not Found");
            header('Location: /404/');
            exit;
        } catch (Exception\Code $e) {
            ob_clean();

            die($e->getErrorView());
        } catch (Exception\Ajax $e) {
            $data = [];
            if (!empty(self::$view)) {
                $data = array_merge($data, self::$view);
            }

            if (!empty($e->view)) {
                $data = array_merge($data, $e->view);
            }

            echo json_encode($data, JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    static function cron($project, $config_file = 'config')
    {
        Definer::init();
        Router::init();

        $config_file = CORE_ROOT . $project . DS . $config_file . '.php';
        if (!file_exists($config_file)) {
            $error = "\n" . 'There is no configuration file in ' . $config_file . "\n\n";
            $error .= "\n";
            die($error);
        }

        return Config::release(include $config_file);
    }
}
