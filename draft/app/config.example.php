<?php
return [
    'site' => [
        'url' => '',
        'public' => ''
    ],
    'db'    => [
        'default' => [
            'host'     => 'localhost',
            'user'     => 'root',
            'password' => '',
            'port'     => 3306,
            'dbname'   => ''
        ]
    ],
    'system' => [
        'app' => 'app/'
    ],
    'login' => [
        'salt'     => 'salt_hash_123',
        'table'    => 'client',
        'superuser' => [
            'login' => 'developer',
            'password' => 'developer'
        ],
        'fields'    => [
            'login'    => 'hash',
            'password' => 'password'
        ]
    ],
    'mail' => [
        'smtp' => [
            'host' => 'ssl://smtp.yandex.ru',
            'port' => 465,
            'login' => '',
            'password' => ''
        ]
    ]
];
