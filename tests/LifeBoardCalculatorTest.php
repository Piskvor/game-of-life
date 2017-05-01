<?php
declare(strict_types=1);

namespace Piskvor\Test;

use PHPUnit\Framework\TestCase;
use Piskvor\LifeBoard;
use Piskvor\LivenessCalculator\LifeBoardCalculator;
use Piskvor\LivenessCalculator\LivenessCalculatorInterface;
use Piskvor\Log;
use Psr\Log\LogLevel;

class LifeBoardCalculatorTest extends TestCase
{
    /** @var Log|\Psr\Log\LoggerInterface */
    private $log;

    /** @var LifeBoard default that's set up for a majority of the tests */
    private $lb;

    /** @var LivenessCalculatorInterface */
    private $livenessCalculator;

    /**
     * Set up a board that's common to most of the tests.
     * Most of the tests are agnostic to the board's parameters,
     * as long as they don't hit the edge.
     */
    protected function setUp()
    {
        $this->log = new Log(LogLevel::DEBUG);
        $this->livenessCalculator = new LifeBoardCalculator();
        $this->lb = new LifeBoard();
        $this->lb->setEdgeSize(20);
        $this->lb->setMaxGenerations(20);
        $this->lb->setSpeciesCount(3);
    }

    /**
     * Test for 0 neighbors - the cell MUST die of boredom
     */
    public function testNextGenerationFrom0Neighbors()
    {
        // no neighbors, die
        $this->lb->addOrganism(5, 5);
        $lbNext = $this->livenessCalculator->getNextBoard($this->lb);
        $this->assertCount(0, $lbNext->getOrganismList());
    }

    /**
     * Test for 1 neighbor - the cell MUST die of boredom
     */
    public function testNextGenerationFrom1Neighbor()
    {
        $this->lb->addOrganism(5, 5);
        $this->lb->addOrganism(5, 6);
        $lbNext2 = $this->livenessCalculator->getNextBoard($this->lb);
        $this->assertCount(0, $lbNext2->getOrganismList());

        // two neighbors, keep (and oscillate)
        $this->lb->addOrganism(5, 7);
        $lbNext3 = $this->livenessCalculator->getNextBoard($this->lb);
        $this->assertCount(3, $lbNext3->getOrganismList());

    }

    /**
     * Test for 2 neighbors - the cell MUST survive
     * (and incidentally, the neighbors will oscillate in next generations,
     * but that's a detail of the cell configuration
     * that's not relevant here)
     */
    public function testNextGenerationFrom2Neighbors()
    {
        for ($y = 5; $y <= 7; $y++) {

            $this->lb->addOrganism(5, $y);
        }
        $lbNext3 = $this->livenessCalculator->getNextBoard($this->lb);
        $this->assertCount(3, $lbNext3->getOrganismList());

    }

    /**
     * Test for 3 neighbors - the surrounded cell MUST survive.
     * As this is a "2x2 square" configuration, by the same rules
     * the other cells MUST survive also.
     *
     * Incidentally, this SHOULD put the board into the stillLife mode in next iteration,
     * but that's an implementation detail not
     */
    public function testNextGenerationFrom3Neighbors()
    {
        // three neighbors, make fourth
        $this->lb->addOrganism(5, 5);
        $this->lb->addOrganism(5, 6);
        $this->lb->addOrganism(6, 5);
        $lbNext3 = $this->livenessCalculator->getNextBoard($this->lb);
        $this->assertCount(4, $lbNext3->getOrganismList());
    }

    /**
     * Test for 4 neighbors - the surrounded cell MUST die
     */
    public function testNextGenerationFrom4Neighbors()
    {
        // here we check for liveness of [5,5] specifically - previous tests were checking for known and expected total counts
        $this->lb->addOrganism(5, 5);
        for ($y = 4; $y <= 6; $y++) {
            $this->lb->addOrganism(4, $y);
        }
        $this->lb->addOrganism(5, 6);
        $this->log->debug($this->lb);
        $lbNext = $this->livenessCalculator->getNextBoard($this->lb);
        $this->log->debug($lbNext);
        $this->assertFalse($lbNext->isLive(5, 5));
    }

    /**
     * Test for 5 neighbors - the surrounded cell MUST die
     */
    public function testNextGenerationFrom5Neighbors()
    {
        // here we check for liveness of [5,5] specifically - previous tests were checking for known and expected total counts
        $this->lb->addOrganism(5, 5);
        for ($y = 4; $y <= 6; $y++) {
            $this->lb->addOrganism(4, $y);
        }
        $this->lb->addOrganism(5, 6);
        $this->lb->addOrganism(5, 4);
        $lbNext = $this->livenessCalculator->getNextBoard($this->lb);
        $this->assertFalse($lbNext->isLive(5, 5));
    }

    /**
     * Test for 6 neighbors - the surrounded cell MUST die
     */
    public function testNextGenerationFrom6Neighbors()
    {
        // here we check for liveness of [5,5] specifically - previous tests were checking for known and expected total counts
        $this->lb->addOrganism(5, 5);
        for ($y = 4; $y <= 6; $y++) {
            $this->lb->addOrganism(4, $y);
        }
        $this->lb->addOrganism(5, 6);
        $this->lb->addOrganism(5, 4);
        $this->lb->addOrganism(6, 4);
        $lbNext = $this->livenessCalculator->getNextBoard($this->lb);
        $this->assertFalse($lbNext->isLive(5, 5));
    }

    /**
     * Test for 7 neighbors - the surrounded cell MUST die
     */
    public function testNextGenerationFrom7Neighbors()
    {
        // here we check for liveness of [5,5] specifically - previous tests were checking for known and expected total counts
        $this->lb->addOrganism(5, 5);
        for ($y = 4; $y <= 6; $y++) {
            $this->lb->addOrganism(4, $y);
        }
        $this->lb->addOrganism(5, 6);
        $this->lb->addOrganism(5, 4);
        $this->lb->addOrganism(6, 4);
        $this->lb->addOrganism(6, 5);
        $lbNext = $this->livenessCalculator->getNextBoard($this->lb);
        $this->assertFalse($lbNext->isLive(5, 5));
    }

    /**
     * Test for 8 neighbors - the surrounded cell MUST die
     */
    public function testNextGenerationFrom8Neighbors()
    {
        // here we check for liveness of [5,5] specifically - previous tests were checking for known and expected total counts
        $this->lb->addOrganism(5, 5);
        for ($y = 4; $y <= 6; $y++) {
            $this->lb->addOrganism(4, $y);
        }
        $this->lb->addOrganism(5, 6);
        $this->lb->addOrganism(5, 4);
        for ($y = 4; $y <= 6; $y++) {
            $this->lb->addOrganism(6, $y);
        }
        $lbNext = $this->livenessCalculator->getNextBoard($this->lb);
        $this->assertFalse($lbNext->isLive(5, 5));
    }

    /**
     * Test that conflicts are resolved randomly
     */
    public function testConflictingNewCells()
    {
        /*
            Here we check for liveness of [5,5] specifically:
                previous tests were checking for known and expected total counts.

            Make two simplest oscillators:
            the following structure will oscillate in a 3-cell line,
            between horizontal and vertical:
            .x. ... .x. ... etc.
            .x. xxx .x. xxx
            .x. ... .x. ...

            However, we are setting the board up
            so that the neighboring oscillators WILL collide (marked "C"):
            .x..o.  .....
            .x..o.  xxCoo
            .x..o.  .....

            In this case, the algorithm
            MUST kill one of the colliding organisms, "randomly."

            Random events are hard to test, but we can make N iterations
            and observe that on average, the result is within expected probability.
            Not perfect, and the result won't be the exact number expected,
            but it should converge towards it.

            Now, we mark "x" as 1 and "O" as 2 internally.
            We could go through the string conversion to be completely safe,
            but we skip this in the interest of efficiency:
            we increment the counter of "O"s observed
            (even organism type; liveness and type count is tested elsewhere),
            and we are looking for a result that's 0.5 within $epsilon .

            Potential caveat: binary FP math could bite us
            in more complex scenarios, but as we're comparing within a range,
            the worst case is "a set of tests right at the edge
            of epsilon is misreported as false positive/negative";
            this would be an issue if we had an actual method of
            choosing a significant epsilon.
        */
        $expectedResult = 0.5;
        $epsilon = 0.01; // because I said so; there's no special significance in this number.
        $expectedResultLow = $expectedResult - $epsilon;
        $expectedResultHigh = $expectedResult + $epsilon;
        $tries = 5000; // A more precise result can be gotten with more iterations, allowing for a smaller $epsilon.
        for ($y = 4; $y <= 6; $y++) {
            $this->lb->addOrganism(4, $y, 'x');
            $this->lb->addOrganism(6, $y, 'O');
        }
        $result = 0;
        for ($t = $tries; $t >= 0; $t--) {
            $lbNext = $this->livenessCalculator->getNextBoard($this->lb);
            $organism = $lbNext->getOrganism(5, 5);
            $this->assertTrue($organism > 0, 'Organism is dead!');
            if ($organism % 2 == 0) {
                $result++;
            }
        }
        $result = $result / $tries;
        // if the selection is random, we should be close to $expectedResult here
        $this->log->debug($result);
        $this->assertTrue($result > $expectedResultLow && $result < $expectedResultHigh, $result);
    }
}