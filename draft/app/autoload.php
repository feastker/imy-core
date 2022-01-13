<?php
spl_autoload_register(
    function ($class) {
        $dirs = array(
            __DIR__ ,
            __DIR__ . '/controller',
            __DIR__ . '/class',
        );

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
);