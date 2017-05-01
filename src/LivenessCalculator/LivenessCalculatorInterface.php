<?php
declare(strict_types=1);

namespace Piskvor\LivenessCalculator;

use Piskvor\LifeBoard;

/**
 * Interface LivenessCalculatorInterface takes a LifeBoard and returns a new one calculated from that.
 * @package Piskvor\LivenessCalculator
 */
interface LivenessCalculatorInterface
{

    public function getNextBoard(LifeBoard $oldBoard): LifeBoard;

}