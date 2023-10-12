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

    private $isHelper = false;

    function __construct($isHelper = false) {
        $this->isHelper = $isHelper;
    }

    abstract function init();

    /**
     * @param $message
     * @param array $data
     */
    function error($message, $data = [])
    {
        return $this->response(
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
        return $this->response(
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
        if($this->isHelper)
            return $data;
        else {
            die(json_encode($data, JSON_UNESCAPED_UNICODE));
        }
    }
}
