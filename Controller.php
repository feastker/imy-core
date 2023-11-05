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

    // Массив для прокидывания переменных в шаблон
    public $v = [];

    // Переменная, определяющая базовый шаблон layout
    public $t = '';

    // При значении true метод response не будет делать die, а будет возвращать массив с данными
    private $isHelper = false;

    protected $request;
    protected $response;

    function __construct($isHelper = false) {
        $this->isHelper = $isHelper;

        $this->response = new Response();
        $this->request = new Request();
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
