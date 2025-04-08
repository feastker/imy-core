<?php

namespace Imy\Core;

class Tools
{

    static function time($time)
    {
        $month_name = array(
            1  => 'января',
            2  => 'февраля',
            3  => 'марта',
            4  => 'апреля',
            5  => 'мая',
            6  => 'июня',
            7  => 'июля',
            8  => 'августа',
            9  => 'сентября',
            10 => 'октября',
            11 => 'ноября',
            12 => 'декабря'
        );

        $month = $month_name[date('n', $time)];
        $day = date('j', $time);
        $year = date('Y', $time);
        $hour = date('G', $time);
        $min = date('i', $time);
        $date = $day . ' ' . $month . ' ' . $year . ' г. в ' . $hour . ':' . $min;
        $dif = time() - $time;

        if ($dif <= 5) {
            return "Только что";
        } elseif ($dif < 59) {
            return $dif . " сек. назад";
        } elseif ($dif / 60 > 1 and $dif / 60 < 59) {
            return round($dif / 60) . " мин. назад";
        } elseif ($dif / 3600 > 1 and $dif / 3600 < 24) {
            return floor($dif / 3600) . " час. назад";
        } elseif ($dif / 3600 / 24 > 1 and $dif / 3600 / 24 < 30) {
            return round($dif / 3600 / 24) . " дн. назад";
        } elseif ($dif / 3600 / 24 / 30 > 1 and $dif / 3600 / 24 / 30 < 30) {
            return round($dif / 3600 / 24 / 30) . " мес. назад";
        } else {
            return $date;
        }
    }

    static function fromArr($arr, $keys)
    {
        $result = [];
        foreach ($arr as $k => $v) {
            if (in_array($k, $keys)) {
                $result[$k] = $v;
            }
        }

        return $result;
    }

    static function s($obj)
    {
        echo '<pre>';
        print_r($obj);
        echo '</pre>';
        exit;
    }

    function charCodeAt($str, $index)
    {
        //not working!

        $char = mb_substr($str, $index, 1, 'UTF-8');
        if (mb_check_encoding($char, 'UTF-8')) {
            $ret = mb_convert_encoding($char, 'UTF-32BE', 'UTF-8');
            return hexdec(bin2hex($ret));
        } else {
            return null;
        }
    }

    static function color($str)
    {
        $code = dechex(crc32($str));
        $code = substr($code, 0, 6);
        return $code;
    }

    static function keyValue($key, $values, $objects)
    {
        $arr = [];

        $values = explode(' ', $values);

        foreach ($objects as $obj) {
            foreach ($values as $value) {
                @$arr[$obj->{$key}] .= $obj->{$value} . ' ';
            }
        }


        return $arr;
    }

    static function transliterate($st)
    {
        $replace = array(
            "'"  => "",
            "`"  => "",
            ","  => "",
            "?"  => "",
            "."  => "",
            "…"  => "",
            " "  => "",
            "»"  => "",
            "«"  => "",
            ":"  => "",
            "("  => "",
            "/"  => "",
            "\\" => "",
            ")"  => "",
            "["  => "",
            "]"  => "",
            "а"  => "a",
            "А"  => "a",
            "б"  => "b",
            "Б"  => "b",
            "в"  => "v",
            "В"  => "v",
            "г"  => "g",
            "Г"  => "g",
            "д"  => "d",
            "Д"  => "d",
            "е"  => "e",
            "Е"  => "e",
            "ж"  => "zh",
            "Ж"  => "zh",
            "з"  => "z",
            "З"  => "z",
            "и"  => "i",
            "И"  => "i",
            "й"  => "y",
            "Й"  => "y",
            "к"  => "k",
            "К"  => "k",
            "л"  => "l",
            "Л"  => "l",
            "м"  => "m",
            "М"  => "m",
            "н"  => "n",
            "Н"  => "n",
            "о"  => "o",
            "О"  => "o",
            "п"  => "p",
            "П"  => "p",
            "р"  => "r",
            "Р"  => "r",
            "с"  => "s",
            "С"  => "s",
            "т"  => "t",
            "Т"  => "t",
            "у"  => "u",
            "У"  => "u",
            "ф"  => "f",
            "Ф"  => "f",
            "х"  => "h",
            "Х"  => "h",
            "ц"  => "c",
            "Ц"  => "c",
            "ч"  => "ch",
            "Ч"  => "ch",
            "ш"  => "sh",
            "Ш"  => "sh",
            "щ"  => "sch",
            "Щ"  => "sch",
            "ъ"  => "",
            "Ъ"  => "",
            "ы"  => "y",
            "Ы"  => "y",
            "ь"  => "",
            "Ь"  => "",
            "э"  => "e",
            "Э"  => "e",
            "ю"  => "yu",
            "Ю"  => "yu",
            "я"  => "ya",
            "Я"  => "ya",
            "і"  => "i",
            "І"  => "i",
            "ї"  => "yi",
            "Ї"  => "yi",
            "є"  => "e",
            "Є"  => "e"
        );
        return iconv("UTF-8", "UTF-8//IGNORE", strtr($st, $replace));
    }

    static function alias($st)
    {
        $str = self::transliterate($st);

        $str = strtolower($str);

        $str = preg_replace('~[^-a-z0-9_]+~u', '-', $str);

        $str = trim($str, "-");

        return $str;
    }

    static function objArray($key, $objects, $group = false)
    {
        $arr = [];

        foreach ($objects as $obj) {
            if ($group == false) {
                $arr[$obj->{$key}] = $obj;
            } else {
                $arr[$obj->{$key}][] = $obj;
            }
        }

        return $arr;
    }

    static function barcode($num)
    {
        include_once CORE_LIBS . 'barcode' . DS . 'vendor' . DS . 'autoload.php';

        $generator = new \Picqer\Barcode\BarcodeGeneratorPNG();
        $html = '<img src="data:image/png;base64,' . base64_encode(
                $generator->getBarcode($num, $generator::TYPE_EAN_13)
            ) . '">';

        return $html;
    }

    static function date_text($stamp)
    {
        $stamp = strtotime($stamp);

        $result = date('d', $stamp) . ' ';
        $result .= self::$guide['months'][date('n', $stamp)]['rod'];

        return $result;
    }

    static function name($string)
    {
        require_once CORE_ROOT . 'core' . DS . 'libs' . DS . 'namer' . DS . 'NCL.NameCase.ru.php';

        $nc = new \NCLNameCaseRu();
        $name = $nc->q($string);

        return $name;
    }

    static function getPageCode($url)
    {
        $handle = curl_init($url);
        curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($handle);
        $httpCode = curl_getinfo($handle, CURLINFO_HTTP_CODE);
        curl_close($handle);

        return $httpCode;
    }

    static function get_include_contents($filename, $vars = [], $eval = false)
    {
        if (is_file($filename)) {
            ob_start();
            if (!empty($vars)) {
                extract($vars, EXTR_SKIP | EXTR_REFS);
            }

            include $filename;
            return empty($eval) ? ob_get_clean() : eval(ob_get_clean());
        }
        return false;
    }

    static function clearPhone($phone)
    {
        return strtr(
            $phone,
            [
                ' ' => '',
                '(' => '',
                ')' => '',
                '-' => '',
                '+' => ''
            ]
        );
    }

    static function point_sum($sum)
    {
        $sum = str_split($sum);
        $sum = array_reverse($sum);
        $i = 1;
        $str = [];
        foreach ($sum as $digit) {
            $str[] = $digit;

            if ($i != 1 && $i % 3 == 0) {
                $str[] = '.';
            }

            $i++;
        }
        if ($str[count($str) - 1] == '.') {
            unset($str[count($str) - 1]);
        }

        $str = array_reverse($str);
        $str = implode('', $str);

        return $str;
    }

    static function password($length = 6, $strength = 0)
    {
        $vowels = '012';
        $consonants = '3456789';
        if ($strength & 1) {
            $consonants .= 'ABCDEFGHJKLMNOPQRSTUVWXYZ';
        } elseif ($strength & 2) {
            $consonants .= '@#$%';
        } elseif ($strength & 3) {
            $consonants .= '!-_=+;.';
        } elseif ($strength & 4) {
            $consonants .= 'abcdefghijklmnopqrstuvwxyz';
        }

        $password = '';
        $alt = time() % 2;
        for ($i = 0; $i < $length; $i++) {
            if ($alt == 1) {
                $password .= $consonants[(rand() % strlen($consonants))];
                $alt = 0;
            } else {
                $password .= $vowels[(rand() % strlen($vowels))];
                $alt = 1;
            }
        }
        return $password;
    }

    static function png2jpg($path, $dist = false)
    {
        if (empty($dist)) {
            $dist = $path;
        }

        $image = imagecreatefrompng($path);
        $bg = imagecreatetruecolor(imagesx($image), imagesy($image));
        imagefill($bg, 0, 0, imagecolorallocate($bg, 255, 255, 255));
        imagealphablending($bg, true);
        imagecopy($bg, $image, 0, 0, 0, 0, imagesx($image), imagesy($image));
        imagedestroy($image);
        $quality = 50; // 0 = worst / smaller file, 100 = better / bigger file
        imagejpeg($bg, $dist, $quality);
        imagedestroy($bg);
    }

    static function lorem($size)
    {
        $text = 'Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.';

        return substr($text, 0, $size);
    }

    static function thumb(
        $src,
        $dest,
        $targetWidth,
        $targetHeight = null,
        $x = 0,
        $y = 0,
        $crop = false,
        $dst_x = 0,
        $dst_y = 0,
        $quality = 100
    ) {
        $image_handlers = [
            2 => [
                'load'    => 'imagecreatefromjpeg',
                'save'    => 'imagejpeg',
                'quality' => $quality
            ],
            3 => [
                'load'    => 'imagecreatefrompng',
                'save'    => 'imagepng',
                'quality' => 0
            ],
            4 => [
                'load' => 'imagecreatefromgif',
                'save' => 'imagegif'
            ],
            18 => [
                'load'    => 'imagecreatefromwebp',
                'save'    => 'imagewebp',
                'quality' => $quality
            ]
        ];

        $type = @exif_imagetype($src);

        // ico
        if ($type == 17) {
            copy($src, $dest);
            return true;
        }

        if (!$type || !@$image_handlers[$type]) {
            return null;
        }

        $image = call_user_func($image_handlers[$type]['load'], $src);
        if (!$image) {
            return null;
        }

        $width = imagesx($image);
        $height = imagesy($image);

        $targetWidth = ($width > $targetWidth ? $targetWidth : $width);
        $targetHeight = ($height > $targetHeight ? $targetHeight : $height);

        if (!empty($targetHeight)) {
            $ratio_height = $height / $targetHeight;
        }

        if (!empty($targetWidth)) {
            $ratio_width = $width / $targetWidth;
        }

        if ($targetHeight == null || (!empty($height) && $ratio_width > $ratio_height && !$crop)) {
            $ratio = $width / $height;
            if ($width > $height) {
                $targetHeight = floor($targetWidth / $ratio);
            } else {
                $targetHeight = $targetWidth;
                $targetWidth = floor($targetWidth * $ratio);
            }
        } elseif ($targetWidth == null || (!empty($width) && $ratio_width < $ratio_height && !$crop)) {
            $ratio = $height / $width;
            if ($width > $height) {
                $targetWidth = floor($targetHeight / $ratio);
            } else {
                $targetWidth = $targetHeight;
                $targetHeight = floor($targetHeight * $ratio);
            }
        }

        $thumbnail = imagecreatetruecolor($targetWidth, $targetHeight);

        // Сохраняем прозрачность для PNG, GIF и WebP
        if ($type == 4 || $type == 3 || $type == 18) {
            imagecolortransparent(
                $thumbnail,
                imagecolorallocate($thumbnail, 0, 0, 0)
            );

            if ($type == 3 || $type == 18) {
                imagealphablending($thumbnail, false);
                imagesavealpha($thumbnail, true);
            }
        }

        imagecopyresampled(
            $thumbnail,
            $image,
            $dst_x,
            $dst_y,
            $x,
            $y,
            $targetWidth,
            $targetHeight,
            !$crop ? $width : $targetWidth,
            !$crop ? $height : $targetHeight
        );

        return call_user_func(
            $image_handlers[$type]['save'],
            $thumbnail,
            $dest,
            $image_handlers[$type]['quality']
        );
    }



    static function repeatChar($char, $quantity)
    {
        $result = '';
        for ($i = 0; $i < $quantity; $i++) {
            $result .= $char;
        }

        return $result;
    }

    static function num2str($num)
    {
        $nul = 'ноль';
        $ten = array(
            array('', 'один', 'два', 'три', 'четыре', 'пять', 'шесть', 'семь', 'восемь', 'девять'),
            array('', 'одна', 'две', 'три', 'четыре', 'пять', 'шесть', 'семь', 'восемь', 'девять'),
        );
        $a20 = array(
            'десять',
            'одиннадцать',
            'двенадцать',
            'тринадцать',
            'четырнадцать',
            'пятнадцать',
            'шестнадцать',
            'семнадцать',
            'восемнадцать',
            'девятнадцать'
        );
        $tens = array(
            2 => 'двадцать',
            'тридцать',
            'сорок',
            'пятьдесят',
            'шестьдесят',
            'семьдесят',
            'восемьдесят',
            'девяносто'
        );
        $hundred = array(
            '',
            'сто',
            'двести',
            'триста',
            'четыреста',
            'пятьсот',
            'шестьсот',
            'семьсот',
            'восемьсот',
            'девятьсот'
        );
        $unit = array( // Units
            array('копейка', 'копейки', 'копеек', 1),
            array('рубль', 'рубля', 'рублей', 0),
            array('тысяча', 'тысячи', 'тысяч', 1),
            array('миллион', 'миллиона', 'миллионов', 0),
            array('миллиард', 'милиарда', 'миллиардов', 0),
        );
        //
        list($rub, $kop) = explode('.', sprintf("%015.2f", floatval($num)));
        $out = array();
        if (intval($rub) > 0) {
            foreach (str_split($rub, 3) as $uk => $v) { // by 3 symbols
                if (!intval($v)) {
                    continue;
                }
                $uk = sizeof($unit) - $uk - 1; // unit key
                $gender = $unit[$uk][3];
                list($i1, $i2, $i3) = array_map('intval', str_split($v, 1));
                // mega-logic
                $out[] = $hundred[$i1]; # 1xx-9xx
                if ($i2 > 1) {
                    $out[] = $tens[$i2] . ' ' . $ten[$gender][$i3];
                } # 20-99
                else {
                    $out[] = $i2 > 0 ? $a20[$i3] : $ten[$gender][$i3];
                } # 10-19 | 1-9
                // units without rub & kop
                if ($uk > 1) {
                    $out[] = self::morph($v, $unit[$uk][0], $unit[$uk][1], $unit[$uk][2]);
                }
            } //foreach
        } else {
            $out[] = $nul;
        }
        $out[] = self::morph(intval($rub), $unit[1][0], $unit[1][1], $unit[1][2]); // rub
        $out[] = $kop . ' ' . self::morph($kop, $unit[0][0], $unit[0][1], $unit[0][2]); // kop
        return trim(preg_replace('/ {2,}/', ' ', join(' ', $out)));
    }

    static function morph($n, $f1, $f2, $f5)
    {
        $n = abs(intval($n)) % 100;
        if ($n > 10 && $n < 20) {
            return $f5;
        }
        $n = $n % 10;
        if ($n > 1 && $n < 5) {
            return $f2;
        }
        if ($n == 1) {
            return $f1;
        }
        return $f5;
    }

    static function getFieldArray($field, $objects, $quotted = false)
    {
        $result = [];
        foreach ($objects as $object) {
            $val = $object->{$field};
            if (!empty($quotted)) {
                $val = '\'' . $val . '\'';
            }
            $result[] = $val;
        }
        return $result;
    }


    static function secureText($text)
    {
        $replaces = [
            '('  => '[',
            ')'  => ']',
            '"'  => '&quot;',
            '\'' => '&quot;'
        ];

        $text = strtr($text, $replaces);
        $text = htmlentities($text);

        return $text;
    }

}
