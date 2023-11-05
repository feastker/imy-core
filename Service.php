<?php

namespace Imy\Core;

use Request;

class Service
{

    protected $request;
    private $isHelper;


    public function __construct($isHelper = false)
    {
        $this->request = new Request();
        $this->isHelper = $isHelper;

    }

    /**
     * @param $message
     * @param array $data
     */
    function error($message, $data = [])
    {
        return $this->response(
            [
                'error' => $message, //Для обратной совместимости
                'status' => 'error',
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
                'status' => 'success',
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
        if ($this->isHelper)
            return $data;
        else {
            die(json_encode($data, JSON_UNESCAPED_UNICODE));
        }
    }
}