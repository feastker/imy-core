<?php
include "./vendor/imy/core/autoload.php";

use Imy\Core\Config;
use Imy\Core\Migrator;
use Imy\Core\Definer;

Definer::init();

$methods = [
    'migrate' => 'Use DB migrations'
];

if (empty($argv[1]) || !isset($methods[$argv[1]])) {
    $error = "\n" . 'Wrong command! Method ' . $argv[1] . ' is not in available methods. You can call one of these:' . "\n\n";
    foreach ($methods as $k => $v) {
        $error .= $k . ' - ' . $v . "\n";
    }
    $error .= "\n";
    die($error);
} else {
    $method = $argv[1];
}

switch ($method) {
    case 'migrate':

        $config_file = './app/config.php';
        if (!file_exists($config_file)) {
            $error = "\n" . 'There is no configuration file in ' . $config_file . "\n\n";
            $error .= "\n";
            die($error);
        }

        Config::release(include $config_file);

        Migrator::migrate('app','./');

        break;
}
