<?php
declare(strict_types=1);


namespace Piskvor;


use fool\echolog\Echolog;
use Psr\Log\LoggerInterface;

/**
 * Class Log provides *a* PSR LoggerInterface
 * without making the rest of the project dependent on
 * a) a specific class
 * b) Registry or
 * c) DI or
 * d) a singleton logger
 * In a tiny project, any of this would over-complicate matters.
 * @package Piskvor
 */
class Log extends Echolog implements LoggerInterface
{

}