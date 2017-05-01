<?php
declare(strict_types=1);

namespace Piskvor\Test;

use Piskvor\Exception\TooManySpeciesException;
use Piskvor\Exception\BoardStateException;

use PHPUnit\Framework\TestCase;
use Piskvor\LifeBoard;
use Piskvor\Log;
use Psr\Log\LogLevel;

class LifeBoardTest extends TestCase
{
    /** @var LifeBoard default that's set up for a majority of the tests */

    /** @var Log */
    private $log;

    /** @var LifeBoard */
    private $lb;

    /**
     * Set up a board that's common to most of the tests.
     * Most of the tests are agnostic to the board's parameters,
     * as long as they don't hit the edge.
     */
    protected function setUp()
    {
        $this->log = new Log(LogLevel::DEBUG);
        $this->lb = new LifeBoard();
        $this->lb->setEdgeSize(20);
        $this->lb->setMaxGenerations(20);
        $this->lb->setSpeciesCount(3);
    }

    /**
     * Test that importing 10 organisms without any conflicts reports back 10 organisms.
     */
    public function testImportOrganism()
    {
        $this->lb->importOrganism(0, 0);
        $this->assertCount(1, $this->lb->getAllOrganisms());
        for ($x = 1; $x < 10; $x++) {
            $this->lb->importOrganism($x, 4);
            $this->assertCount($x + 1, $this->lb->getAllOrganisms());
        }
    }

    /**
     * Test that importing 11 organisms of the same species into 10 cells
     * only reports back 10 organisms - only 1 org per cell.
     */
    public function testImportOrganismWithOverwrite()
    {
        $x = 0;
        // imported 1
        $this->lb->importOrganism(0, 0);
        $x++;

        $this->assertCount(1, $this->lb->getAllOrganisms());
        // import +9 more
        for (; $x < 10; $x++) {
            // $x is both a counter and coordinate here
            $this->lb->importOrganism($x, 4);
        }

        // import +1 more _over_ an existing one
        $this->lb->importOrganism(5,4);
        //$x++; // this MUST NOT bump the count!

        // there should now be 10 organisms
        $this->assertCount($x, $this->lb->getAllOrganisms());
    }

    /**
     * Test that it's not possible to import organisms
     * that are outside the board in negative space
     */
    public function testImportInvalidCoordsOutsideBoardNegative()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->lb->importOrganism(-1, 0);
    }

    /**
     * Test that it's not possible to import organisms
     * that are outside the board beyond its size
     */
    public function testImportInvalidCoordsOutsideBoardPositive()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->lb->importOrganism(5, 30);
    }


    /**
     * Test that we're only allowed to import as many organism types
     * as defined by the /life/world/species preamble.
     * Although our implementation could work with dynamically expanding species count,
     * we rely on the preamble to alert us that something is fishy in such a case.
     * (In other words, "your import species count is broken" is assumed to be
     * easier to fix than "we took your ambiguous import and here's your output
     * with our unvoiced assumptions baked in.")
     */
    public function testImportTooManyTypes()
    {
        $this->lb->importOrganism(1, 0, 'a');
        $this->lb->importOrganism(5, 0, 'b');
        $this->lb->importOrganism(2, 0, 'c');
        $this->expectException(TooManySpeciesException::class);
        $this->lb->importOrganism(2, 0, 'd');
    }

    /**
     * Test that importing an insane size of the world doesn't work.
     * While we could theoretically try to import a size > PHP_INT_MAX,
     * we would hit many limits before that;
     * so stick with the lower bound of insanity.
     */
    public function testImportBadWorldSize()
    {
        $lb = new LifeBoard();
        $this->expectException(\InvalidArgumentException::class);
        $lb->setEdgeSize(0);
    }

    /**
     * Test that some iterations are required:
     * negatives make no sense under current definition.
     * Although it is theoretically possible to make the simulation run backwards,
     * it is certain that some generation steps are lossy
     * (M possible states of gen N result in 1 state in gen N+1 - see e.g. a glider stopping in a corner as a 2x2)
     * Therefore, the algorithm is a trapdoor function, not trivially reversible even for 1 species.
     * To make matters worse, there MAY be conflicts between species,
     * and these are resolved randomly, making the algorithm indeterministic.
     *
     * Therefore, a positive, integer number of iterations is required (the integer part is enforced by parameter type).
     */
    public function testImportBadWorldNegativeGenerations()
    {
        $lb = new LifeBoard();
        $this->expectException(\InvalidArgumentException::class);
        $lb->setMaxGenerations(-10);
    }

    /**
     * Test that there MUST be at least one species.
     * Theoretically, we could accept 0 species,
     * set the board empty and stillLife,
     * but that sounds like an error in input data: fail fast here,
     * rather than pretend computation and spit out an identical empty board.
     */
    public function testImportBadWorldNoSpecies()
    {
        $lb = new LifeBoard();
        $this->expectException(\InvalidArgumentException::class);
        $lb->setSpeciesCount(0);
    }

    /**
     * Test that importing an organism into an uninitialized board MUST fail
     */
    public function testImportOrganismBeforeWorld()
    {
        $lb = new LifeBoard();
        $lb->setMaxGenerations(10);
        $this->expectException(BoardStateException::class);
        $lb->importOrganism(2, 2, 'x');
    }

    /**
     * Test for 0 neighbors - the cell MUST die of boredom
     */
    public function testNextGenerationFrom0Neighbors()
    {
        // no neighbors, die
        $this->lb->importOrganism(5, 5);
        $lbNext = $this->lb->getNextBoard();
        $this->assertCount(0, $lbNext->getAllOrganisms());
    }

    /**
     * Test for 1 neighbor - the cell MUST die of boredom
     */
    public function testNextGenerationFrom1Neighbor()
    {
        $this->lb->importOrganism(5, 5);
        $this->lb->importOrganism(5, 6);
        $lbNext2 = $this->lb->getNextBoard();
        $this->assertCount(0, $lbNext2->getAllOrganisms());

        // two neighbors, keep (and oscillate)
        $this->lb->importOrganism(5, 7);
        $lbNext3 = $this->lb->getNextBoard();
        $this->assertCount(3, $lbNext3->getAllOrganisms());

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

            $this->lb->importOrganism(5, $y);
        }
        $lbNext3 = $this->lb->getNextBoard();
        $this->assertCount(3, $lbNext3->getAllOrganisms());

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
        $this->lb->importOrganism(5, 5);
        $this->lb->importOrganism(5, 6);
        $this->lb->importOrganism(6, 5);
        $lbNext3 = $this->lb->getNextBoard();
        $this->assertCount(4, $lbNext3->getAllOrganisms());
    }

    /**
     * Test for 4 neighbors - the surrounded cell MUST die
     */
    public
    function testNextGenerationFrom4Neighbors()
    {
        // here we check for liveness of [5,5] specifically - previous tests were checking for known and expected total counts
        $this->lb->importOrganism(5, 5);
        for ($y = 4; $y <= 6; $y++) {
            $this->lb->importOrganism(4, $y);
        }
        $this->lb->importOrganism(5, 6);
        $this->log->debug($this->lb);
        $lbNext = $this->lb->getNextBoard();
        $this->log->debug($lbNext);
        $this->assertFalse($lbNext->isLive(5, 5));
    }

    /**
     * Test for 5 neighbors - the surrounded cell MUST die
     */
    public
    function testNextGenerationFrom5Neighbors()
    {
        // here we check for liveness of [5,5] specifically - previous tests were checking for known and expected total counts
        $this->lb->importOrganism(5, 5);
        for ($y = 4; $y <= 6; $y++) {
            $this->lb->importOrganism(4, $y);
        }
        $this->lb->importOrganism(5, 6);
        $this->lb->importOrganism(5, 4);
        $lbNext = $this->lb->getNextBoard();
        $this->assertFalse($lbNext->isLive(5, 5));
    }

    /**
     * Test for 6 neighbors - the surrounded cell MUST die
     */
    public
    function testNextGenerationFrom6Neighbors()
    {
        // here we check for liveness of [5,5] specifically - previous tests were checking for known and expected total counts
        $this->lb->importOrganism(5, 5);
        for ($y = 4; $y <= 6; $y++) {
            $this->lb->importOrganism(4, $y);
        }
        $this->lb->importOrganism(5, 6);
        $this->lb->importOrganism(5, 4);
        $this->lb->importOrganism(6, 4);
        $lbNext = $this->lb->getNextBoard();
        $this->assertFalse($lbNext->isLive(5, 5));
    }

    /**
     * Test for 7 neighbors - the surrounded cell MUST die
     */
    public
    function testNextGenerationFrom7Neighbors()
    {
        // here we check for liveness of [5,5] specifically - previous tests were checking for known and expected total counts
        $this->lb->importOrganism(5, 5);
        for ($y = 4; $y <= 6; $y++) {
            $this->lb->importOrganism(4, $y);
        }
        $this->lb->importOrganism(5, 6);
        $this->lb->importOrganism(5, 4);
        $this->lb->importOrganism(6, 4);
        $this->lb->importOrganism(6, 5);
        $lbNext = $this->lb->getNextBoard();
        $this->assertFalse($lbNext->isLive(5, 5));
    }

    /**
     * Test for 8 neighbors - the surrounded cell MUST die
     */
    public
    function testNextGenerationFrom8Neighbors()
    {
        // here we check for liveness of [5,5] specifically - previous tests were checking for known and expected total counts
        $this->lb->importOrganism(5, 5);
        for ($y = 4; $y <= 6; $y++) {
            $this->lb->importOrganism(4, $y);
        }
        $this->lb->importOrganism(5, 6);
        $this->lb->importOrganism(5, 4);
        for ($y = 4; $y <= 6; $y++) {
            $this->lb->importOrganism(6, $y);
        }
        $lbNext = $this->lb->getNextBoard();
        $this->assertFalse($lbNext->isLive(5, 5));
    }

    /**
     * Test that conflicts are resolved randomly
     */
    public
    function testConflictingNewCells()
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
            $this->lb->importOrganism(4, $y, 'x');
            $this->lb->importOrganism(6, $y, 'O');
        }
        $result = 0;
        for ($t = $tries; $t >= 0; $t--) {
            $lbNext = $this->lb->getNextBoard();
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