<?php
/**
 * Created by PhpStorm.
 * User: rkrishtun
 * Date: 16.08.18
 * Time: 11:45
 */

namespace Imy\Core;


abstract class Controller
{

    public $v = [];
    public $t = '';

    abstract function init();

    /**
     * @param $message
     * @param array $data
     */
    function error($message, $data = [])
    {
        $this->response(
            [
                'error'   => $message, //Для обратной совместимости
                'status'  => 'error',
                'message' => $message
            ] + $data
        );
    }

    /**
     * @param $message
     * @param array $data
     */
    function success($message, $data = [])
    {
        $this->response(
            [
                'success' => $message, //Для обратной совместимости
                'status'  => 'success',
                'message' => $message
            ] + $data
        );
    }

    /**
     * @param $data
     */
    function response($data)
    {
        header('Content-Type: application/json');
        die(json_encode($data, JSON_UNESCAPED_UNICODE));
    }
}
