<?php
require_once __DIR__ . '/src/bootstrap.php';

use Piskvor\GolApp;
use Piskvor\Log;

$log = new Log();
if ($argc < 2) {
    $log->error('Usage: php life.php filename.xml');
    die(1);
}
$inputFile = realpath($argv[1]);
$application = new GolApp($inputFile);
$application->run();