<?php
use Imy\Core\Controller;
use Imy\Core\Router;
use Imy\Core\UI;
use Imy\Core\Tools;
use Imy\Core\Data;
use Imy\Core\User;

use Storage;

class CommonController extends Controller {

    function first() {
        define('APP',ROOT . '..' . DS . 'app' . DS);
        define('JS',APP . 'assets' . DS . 'js' . DS);

        Storage::init();
    }

    function init() {
        $route = strtolower(Router::$route);

        //Подключаем стили и скрипты для страницы, если они есть.
        $pageStyle = APP . 'assets' . DS . 'sass' . DS . 'page' . DS . $route . '.scss';
        $pageScript = APP . 'assets' . DS . 'js' . DS . 'page' . DS . $route . '.js';

        if(file_exists($pageStyle))
            $this->v['style'] = $route;

        if(file_exists($pageScript))
            $this->v['script'] = $route;

    }

    function ajax() {
        if(User::$auth && isset($_GET['upload']))
            $this->upload();
    }

    function ajax_get_modal() {

        $data = [];
        $method = 'modal_' . str_replace('.','_',$_POST['template']);
        if(method_exists($this,$method))
            $data = $this->{$method}();

        $controller_name = ucfirst(Router::$route) . 'Controller';
        if(class_exists($controller_name)) {
            $controller = new $controller_name();
            if(method_exists($controller, $method)) {
                $tmp = $controller->{$method}();
                $data = array_merge($data,$tmp);
            }
        }

        $commonParams = [
            'ui_hash' => 'u' . md5(uniqid()),
            'params' => [],
        ];

        $script = false;
        $modal = explode('.',$_POST['template']);
        $scriptFile = JS . 'modal' . DS . array_pop($modal) . '.js';
        if(file_exists($scriptFile))
            $script = Tools::get_include_contents($scriptFile,$commonParams);

        $template = VIEW . str_replace('.',DS,$_POST['template']) . '.php';
        $content = Tools::get_include_contents($template,$commonParams + $data + (!empty($_POST['data']) ? $_POST['data'] : []));
        $id = md5(uniqid());
        $html = UI::show('modal',$commonParams + ['content' => $content,'id' => $id,'header' => $_POST['header'],'static' => !empty($_POST['data']['static']) ? true : false,'size' => $_POST['size'],'script' => $script,'tpl' => str_replace('.','-',$_POST['template'])]);

        $this->v['html'] = $html;
        $this->v['id'] = $id;
    }

    function upload() {

        if(!empty($_FILES)) {

            $dir = UP . 'tmp' . DS ;

            $images_ext = ['jpg','jpeg','png'];

            foreach($_FILES as $file) {

                $size = $file['size'] / 1024 / 1024;
                if($size > 3) {
                    $this->error('Максимальный размер файла изображения - 3Мб');
                }

                $name = explode('.',$file['name']);
                $ext = array_pop($name);
                $name = md5(uniqid()) . '.' . $ext;

                if (in_array(strtolower($ext), $images_ext)) {

                    Tools::thumb($file['tmp_name'], $dir . $name, 2000,2000);
                } else {
                    $this->error('Неверный формат изображения. Возможные форматы: JPEG, JPG, PNG');
                }
                $this->v['name'] = $name;
                return true;
            }
        }

    }

}
