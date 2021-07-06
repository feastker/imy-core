<?php
/**
 * Created by PhpStorm.
 * User: Feast
 * Date: 18.08.2018
 * Time: 12:58
 */

namespace Imy\Core\Exception;

use Imy\Core\Tools;

class Code extends \Exception
{

    protected $data;

    protected $error;

    public function __construct($error = 'Error Code', $data = [])
    {
        if (!is_array($data)) {
            $data = ['data' => $data];
        }

        $this->data = $data;
        $this->error = $error;
    }

    public function getInfo()
    {
        $result = [
            'post'   => $_POST,
            'cookie' => $_COOKIE,
            'get'    => $_GET,
        ];

        return $this->data + $result;
    }

    public function getError()
    {
        return $this->error;
    }

    public function getErrorView()
    {
        $styles = [
            '',
            'theme/app-assets/vendors/css/vendors.min',
            'theme/app-assets/css/bootstrap',
            'theme/app-assets/css/bootstrap-extended',
            'theme/app-assets/css/colors',
            'theme/app-assets/css/components',
            'theme/app-assets/css/themes/dark-layout',
            'theme/app-assets/css/themes/semi-dark-layout',
            'theme/app-assets/css/core/menu/menu-types/horizontal-menu',
            'theme/app-assets/css/core/colors/palette-gradient',
            'theme/app-assets/vendors/css/extensions/toastr',
            'theme/app-assets/css/plugins/extensions/toastr',
        ];

        $style = '';
        foreach ($styles as $s) {
            $style .= Tools::get_include_contents(
                CORE_ROOT . 'core' . DS . 'example_admin' . DS . 'public' . DS . $s . '.css'
            );
        }

        $style .= Tools::get_include_contents(CORE_ROOT . 'core' . DS . 'misc' . DS . 'code_error.css');


        $scripts = [
            'theme/app-assets/vendors/js/vendors.min',
            'theme/app-assets/vendors/js/ui/jquery.sticky',
            'theme/app-assets/js/core/app-menu',
            'theme/app-assets/js/core/app',
            'theme/app-assets/js/scripts/components',
        ];

        $script = '';
        foreach ($scripts as $s) {
            $script .= Tools::get_include_contents(
                CORE_ROOT . 'core' . DS . 'example_admin' . DS . 'public' . DS . $s . '.js'
            );
        }

        $arrays = [
            'post'    => $_POST,
            'get'     => $_GET,
            'cookie'  => $_COOKIE,
            'session' => $_SESSION
        ];

        $data = [
            'error'  => $this->error,
            'style'  => $style,
            'script' => $script,
            'arrays' => $arrays,
            'data'   => $this->data,
            'trace'  => $this->getTraceAsString()
        ];

        return Tools::get_include_contents(CORE_ROOT . 'core' . DS . 'misc' . DS . 'error_template.php', $data);
    }

}
