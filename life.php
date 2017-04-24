<?php
require_once 'src/bootstrap.php';

use Piskvor\GolApp;

if ($argc < 2) {
    throw new InvalidArgumentException('Input file required');
}
$inputFile = realpath($argv[1]);
$application = new GolApp($inputFile);
$application->run();