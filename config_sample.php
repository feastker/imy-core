<?php

return [
    'db' => [
        'default' => [
            'driver'   => 'mysql',  // mysql (работает для MySQL и MariaDB) или можно явно указать 'mariadb'
            'host'     => 'localhost',
            'port'     => 3306,
            'dbname'   => '',       // имя базы данных
            'user'     => '',
            'password' => '',
            'charset'  => 'utf8mb4', // кодировка (utf8, utf8mb4)
            // 'persistent' => false,  // постоянные соединения (опционально)
            // 'ca'         => '',     // путь к SSL сертификату (опционально)
        ]
    ]
];
