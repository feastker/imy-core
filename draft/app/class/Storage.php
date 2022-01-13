<?php

use Spatie\ImageOptimizer\OptimizerChainFactory;
use Imy\Core\Tools;

class Storage {

    static $dir;
    static $tmp;
    static $url = '/uploads/storage/';
    static $optimizer;

    static $exts = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif'
    ];

    static function init() {

        self::$dir = UP . 'storage' . DS;
        self::$tmp = self::$dir . 'tmp' . DS;
    }

    static function optimize($file) {
        if(empty(self::$optimizer)) {
            self::$optimizer = OptimizerChainFactory::create();
        }

        return self::$optimizer->optimize($file);
    }

    static function store($file) {
        if(is_string($file) && strpos($file,'http') !== false) {
            $buffer = file_get_contents($file);
            $finfo = new \finfo(FILEINFO_MIME_TYPE);

            $type = $finfo->buffer($buffer);
            $ext = self::$exts[$type];

            $ch = curl_init($file);
            $tmp_name = md5(uniqid());
            $fp = fopen(self::$tmp . $tmp_name, 'wb');
            curl_setopt($ch, CURLOPT_FILE, $fp);
            curl_setopt($ch, CURLOPT_HEADER, 0);
            curl_exec($ch);
            curl_close($ch);
            fclose($fp);
            $file = self::$tmp . $tmp_name;
        }
        elseif(is_string($file) && strpos($file,'base64') !== false) {

            $image_array_1 = explode(";", $_POST['file']);
            $image_array_2 = explode(",", $image_array_1[1]);
            $data = base64_decode($image_array_2[1]);
            $tmp_name = md5(uniqid());
            file_put_contents(self::$tmp . $tmp_name, $data);

            $buffer = file_get_contents(self::$tmp . $tmp_name);
            $finfo = new \finfo(FILEINFO_MIME_TYPE);

            $type = $finfo->buffer($buffer);
            $ext = self::$exts[$type];

            $file = self::$tmp . $tmp_name;
        }
        elseif(is_array($file)) {

        }
        else {
            $file = explode('/',$file);
            $file = array_pop($file);
            $file = self::$tmp . $file;

            $buffer = file_get_contents($file);
            $finfo = new \finfo(FILEINFO_MIME_TYPE);

            $type = $finfo->buffer($buffer);
            $ext = self::$exts[$type];

        }

        if(empty($ext))
            throw new \Exception('Storage: Wrong file type');

        if(empty($file))
            throw new \Exception('Storage: Missing temp file');

        $name = md5(uniqid());
        $dirs = str_split($name,2);

        $i = 0;
        $dir = self::$dir;
        do {
            $dir .= $dirs[$i] . DS;
            if(!is_dir($dir)) {
                mkdir($dir,0755);
            }

            $i++;
        }
        while($i < 5);

        $name .= '.' . $ext;
        $newFile = $dir . $name;

        copy($file,$newFile);
        self::optimize($newFile);



        return $name;
    }

    static function get($file) {
        $dirs = str_split($file,2);

        $i = 0;
        $dir = '';
        do {
            $dir .= $dirs[$i] . '/';
            $i++;
        }
        while($i < 5);

        return self::$url . $dir . $file;
    }
}
