<?php

spl_autoload_register(
    function ($searchClass) {

        $class = $searchClass;

        // Imy\Core loader
        $ns = 'Imy\Core';
        $prefixes = array(
            "{$ns}\\" => array(
                __DIR__,
                __DIR__ . '/tests',
            ),
        );
        foreach ($prefixes as $prefix => $dirs) {
            $prefix_len = strlen($prefix);
            if (substr($class, 0, $prefix_len) !== $prefix) {
                continue;
            }
            $class = substr($class, $prefix_len);
            $part = str_replace('\\', DIRECTORY_SEPARATOR, $class) . '.php';
            foreach ($dirs as $dir) {
                $dir = str_replace('/', DIRECTORY_SEPARATOR, $dir);
                $file = $dir . DIRECTORY_SEPARATOR . $part;

                if (is_readable($file)) {
                    require $file;
                    return;
                }
            }
        }

        // Project loaders
        $dirs = [
            '_validator',
            '_class',
            '_repository',
            '_model',
            '_service'
        ];

        $part = str_replace('\\', DIRECTORY_SEPARATOR, $searchClass) . '.php';

        foreach($dirs as $mainDir) {
            $replaceName = ucfirst(str_replace('_','',$mainDir)) . DIRECTORY_SEPARATOR;

            $levels = [3,4];
            foreach($levels as $level) {
                $dir = dirname(__DIR__, $level) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $mainDir);
                $file = $dir . DIRECTORY_SEPARATOR . str_replace($replaceName, '', $part);

                if (is_readable($file)) {

                    require $file;
                    return;
                }
            }
        }
    }
);

include 'functions.php';
