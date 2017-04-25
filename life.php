<?php
require_once 'src/bootstrap.php';

use Piskvor\GolApp;

if ($argc < 2) {
    echo "Usage: php life.php filename.xml";
    die(1);
}
$inputFile = realpath($argv[1]);
$application = new GolApp($inputFile);
$application->run();