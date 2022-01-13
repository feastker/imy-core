<?php
use Imy\Core\User;
use Imy\Core\Router;
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <title><?=($title ?? '')?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0,user-scalable=0"/>
    <link href="/css/<?=(empty($style) ? 'common' : $style)?>.css?v<?=VER?>" rel="stylesheet">
</head>
<body class="preload">
<? if(User::$auth): ?>
    <? include tpl('common.header')?>

    <div class="flex-wrapper">
        <? include tpl(strtolower(Router::$route) . '.' . (!empty($tpl) ? $tpl : 'init')); ?>
    </div>

    <? include tpl('common.footer')?>
<? else: ?>
    <? include tpl(strtolower(Router::$route) . '.' . (!empty($tpl) ? $tpl : 'init')); ?>
<? endif; ?>

<script src="/js/<?=(empty($script) ? 'common' : $script)?>.js?v<?=VER?>"></script>
</body>
</html>
