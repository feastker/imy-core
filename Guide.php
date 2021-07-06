<?php

namespace Imy\Core;

class Guide
{

    static $guides = [
        'horoscopes' => [
            'signs' => [
                1  => [
                    'name'  => 'Овен',
                    'rod'   => 'Овна',
                    'alias' => 'oven',
                    'eng'   => 'aries'
                ],
                2  => [
                    'name'  => 'Телец',
                    'rod'   => 'Тельца',
                    'alias' => 'telets',
                    'eng'   => 'taurus'
                ],
                3  => [
                    'name'  => 'Близнецы',
                    'rod'   => 'Близнецов',
                    'alias' => 'bliznetsi',
                    'eng'   => 'gemini'
                ],
                4  => [
                    'name'  => 'Рак',
                    'rod'   => 'Рака',
                    'alias' => 'rac',
                    'eng'   => 'cancer'
                ],
                5  => [
                    'name'  => 'Лев',
                    'rod'   => 'Льва',
                    'alias' => 'lev',
                    'eng'   => 'leo'
                ],
                6  => [
                    'name'  => 'Дева',
                    'rod'   => 'Девы',
                    'alias' => 'deva',
                    'eng'   => 'virgo'
                ],
                7  => [
                    'name'  => 'Весы',
                    'rod'   => 'Весов',
                    'alias' => 'vesy',
                    'eng'   => 'libra'
                ],
                8  => [
                    'name'  => 'Скорпион',
                    'rod'   => 'Скорпиона',
                    'alias' => 'scorpion',
                    'eng'   => 'scorpio'
                ],
                9  => [
                    'name'  => 'Стрелец',
                    'rod'   => 'Стрельца',
                    'alias' => 'strelets',
                    'eng'   => 'sagitarius'
                ],
                10 => [
                    'name'  => 'Козерог',
                    'rod'   => 'Козерога',
                    'alias' => 'kozerog',
                    'eng'   => 'capricornus'
                ],
                11 => [
                    'name'  => 'Водолей',
                    'rod'   => 'Водолея',
                    'alias' => 'vodoley',
                    'eng'   => 'aquarius'
                ],
                12 => [
                    'name'  => 'Рыбы',
                    'rod'   => 'Рыб',
                    'alias' => 'riby',
                    'eng'   => 'pisces'
                ],
            ]
        ],
        'months'     => [
            1  => [
                'full'  => 'Январь',
                'short' => 'Янв',
                'rod'   => 'Января',
                'pred'  => 'Январе',
                'eng'   => 'January'
            ],
            2  => [
                'full'  => 'Февраль',
                'short' => 'Фев',
                'rod'   => 'Февраля',
                'pred'  => 'Феврале',
                'eng'   => 'February'
            ],
            3  => [
                'full'  => 'Март',
                'short' => 'Мар',
                'rod'   => 'Марта',
                'pred'  => 'Марте',
                'eng'   => 'March'
            ],
            4  => [
                'full'  => 'Апрель',
                'short' => 'Апр',
                'rod'   => 'Апреля',
                'pred'  => 'Апреле',
                'eng'   => 'April'
            ],
            5  => [
                'full'  => 'Май',
                'short' => 'Май',
                'rod'   => 'Мая',
                'pred'  => 'Мае',
                'eng'   => 'May'
            ],
            6  => [
                'full'  => 'Июнь',
                'short' => 'Июн',
                'rod'   => 'Июня',
                'pred'  => 'Июне',
                'eng'   => 'June'
            ],
            7  => [
                'full'  => 'Июль',
                'short' => 'Июл',
                'rod'   => 'Июля',
                'pred'  => 'Июле',
                'eng'   => 'July'
            ],
            8  => [
                'full'  => 'Август',
                'short' => 'Авг',
                'rod'   => 'Августа',
                'pred'  => 'Августе',
                'eng'   => 'August'
            ],
            9  => [
                'full'  => 'Сентябрь',
                'short' => 'Сен',
                'rod'   => 'Сентября',
                'pred'  => 'Сентябре',
                'eng'   => 'September'
            ],
            10 => [
                'full'  => 'Октябрь',
                'short' => 'Окт',
                'rod'   => 'Октября',
                'pred'  => 'Октябре',
                'eng'   => 'October'
            ],
            11 => [
                'full'  => 'Ноябрь',
                'short' => 'Ноя',
                'rod'   => 'Ноября',
                'pred'  => 'Ноябре',
                'eng'   => 'November'
            ],
            12 => [
                'full'  => 'Декабрь',
                'short' => 'Дек',
                'rod'   => 'Декабря',
                'pred'  => 'Декабре',
                'eng'   => 'December'
            ]
        ],
        'weeks'      => [
            1 => [
                'full'  => 'Понедельник',
                'short' => 'Пн'
            ],
            2 => [
                'full'  => 'Вторник',
                'short' => 'Вт'
            ],
            3 => [
                'full'  => 'Среда',
                'short' => 'Ср'
            ],
            4 => [
                'full'  => 'Четверг',
                'short' => 'Чт'
            ],
            5 => [
                'full'  => 'Пятница',
                'short' => 'Пт'
            ],
            6 => [
                'full'  => 'Суббота',
                'short' => 'Сб'
            ],
            7 => [
                'full'  => 'Воскресенье',
                'short' => 'Вс'
            ]
        ]
    ];

    static function get($str)
    {
        $str = explode('.', $str);

        $data = self::$guides;

        foreach ($str as $part) {
            if (!empty($data[$part])) {
                $data = $data[$part];
            } else {
                $data = false;
            }
        }

        return $data;
    }
}
