<?php

use Imy\Core\Model;
use Imy\Core\Exception;

function M($name, $database = null)
{
    return new Model($name, $database);
}

function s($obj)
{
    echo '<pre>';
    print_r($obj);
    echo '</pre>';
    exit;
}

function d($obj)
{
    throw new Exception\Code('Отладка', $obj);
}

function flog($info)
{
    $fp = fopen(getCWD() . DS . 'DEBUG.txt', 'a+');
    fwrite($fp, print_r($info, 1) . "\n");
    fclose($fp);
}

function console($msg, $return = false)
{
    $msg = $msg . "\n";

    if ($return) {
        return $msg;
    } else {
        echo $msg;
    }
}

function success($message = '', $data = [])
{
    $json = [
        'success' => true,
        'message' => $message,
        'data'    => $data
    ];

    die(json_encode($json, JSON_UNESCAPED_UNICODE));
}

function error($message = '', $data = [])
{
    $json = [
        'error'   => true,
        'message' => $message,
        'data'    => $data
    ];

    die(json_encode($json, JSON_UNESCAPED_UNICODE));
}
