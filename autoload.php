<?php

spl_autoload_register(
    function ($class) {
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
    }
);

include 'functions.php';
