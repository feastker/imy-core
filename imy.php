<?php

include "autoload.php";

use Imy\Core\Config;
use Imy\Core\Migrator;
use Imy\Core\Definer;


Definer::init();

$methods = [

    'migrate' => 'Use DB migrations',
    'test'    => 'Just for test',
    'dbcheck' => 'Check DB consistansion'

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

        if (empty($argv[2]) || !is_dir('../' . $argv[2])) {
            $error = "\n" . 'Wrong command! Miss project parameter or it\'s wrong. Available projects:' . "\n\n";

            $skip = array('.', '..', '.idea', 'core');
            $files = scandir('../');
            foreach ($files as $file) {
                if (!in_array($file, $skip)) {
                    $error .= $file . "\n";
                }
            }

            $error .= "\n";
            die($error);
        }

        $config_file = '../' . $argv[2] . '/config.php';
        if (!file_exists($config_file)) {
            $error = "\n" . 'There is no configuration file in ' . $config_file . "\n\n";
            $error .= "\n";
            die($error);
        }

        Config::release(include $config_file);

        Migrator::migrate('core');
        Migrator::migrate($argv[2]);

        break;

    case 'test':

        die('test');
        break;

    case 'dbcheck':


        print_r($argv);
        die('q' . "\n");

        break;
}
