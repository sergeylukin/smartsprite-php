<?php

define('DS', DIRECTORY_SEPARATOR);
define('__BASE_PATH', realpath(dirname(__FILE__)));
define('__LIB_PATH', dirname(__BASE_PATH));

require_once(__LIB_PATH . DS . 'smartsprite.php');

new Smartsprite(__BASE_PATH . DS . 'css' . DS . 'style.css');
