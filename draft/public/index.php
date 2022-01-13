<?php
include "../vendor/autoload.php";
include "../vendor/imy/core/autoload.php";
include "../app/autoload.php";
include "../app/functions.php";
include "../app/defines.php";

use Imy\Core\Core;

define('VER',include '../version.php');

Core::init('../app/config');
