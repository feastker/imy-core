<?php

namespace Imy\Core;

class Data
{

    static $errors;

    static $types = [
        'login'    => [
            'reg' => '^[\w\d@\+\-\.\ \%]+$'
        ],
        'phone'    => [
            'reg' => '^[\d\-\s\+\(\)]+$'
        ],
        'name'     => [
            'reg' => '^[\w\-\s\.]+$'
        ],
        'password' => [
            'reg' => '^[^\(\)\'"]+$'
        ],
        'alias'    => [
            'reg' => '^[\w\d\-\.\+_]+$'
        ],
        'mail'     => [
            'reg' => '^[\w\d\.\-\+]+@[\w\d\.\-\+]+\.[\w\d\.\-\+]+$'
        ],
        'date'     => [
            'reg' => '^[\d\-\.\s]+$'
        ],
        'time'     => [
            'reg' => '^[\d\:]+$'
        ],
        'num'      => [
            'reg' => '^\-*[\d]+$'
        ]
    ];

    static function check($vals, $type = false, $opts = [])
    {
        $error = [];

        if (!is_array($vals)) {
            $vals = [[$vals, $type, $opts]];
        }


        foreach ($vals as $test) {
            $value = $test[0];
            $type = $test[1];
            $opts = !empty($test[2]) ? $test[2] : [];

            if (empty(self::$types[$type])) {
                throw new Exception\Code('Wrong field type: ' . $type, ['vals' => $vals]);
            }

            $test = self::$types[$type];

            if (isset($opts['required']) && empty($value)) {
                $error[] = $value;
                continue;
            }

            if (!empty($test['reg'])) {
                if (!is_array($test['reg'])) {
                    $test['reg'] = [$test['reg']];
                }

                foreach ($test['reg'] as $reg) {
                    if (!preg_match('/' . $reg . '/ui', $value)) {
                        $error[] = $value;

                        break;
                    }
                }
            }
        }

        self::$errors = $error;
        return empty($error) ? true : false;
    }


}
