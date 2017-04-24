<?php
require_once __DIR__ . '/../vendor/autoload.php';

use Tracy\Debugger;
error_reporting(E_ALL);
Debugger::enable(Debugger::DEVELOPMENT, __DIR__ . '/../log');
Debugger::$strictMode = TRUE;

