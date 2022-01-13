<?php
function svg($src, $opts = [],$folder = 'icon')
{

    $dir = ROOT . 'img' . DS . $folder . DS;
    $file = $dir . $src . '.svg';

    $svg = file_get_contents($file);

    $optStr = [];
    if (!empty($opts['class'])) {
        $optStr[] = 'class="' . $opts['class'] . '"';
    }

    if (!empty($opts['data'])) {
        $data = [];
        foreach ($opts['data'] as $key => $val) {
            $data[] = 'data-' . $key . '=\'' . $val . '\'';
        }
        $optStr[] = implode(' ', $data);
    }

    $replaces = ['<svg ' => '<svg ' . implode(' ', $optStr)];
    $svg = strtr($svg, $replaces);

    return $svg;
}

function tpl($tpl) {
    return VIEW . str_replace('.',DS,$tpl) . '.php';
}

function money($val) {
    return number_format(round($val), 0, ',', '&nbsp;');
}
