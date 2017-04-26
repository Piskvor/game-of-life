<?php
declare(strict_types=1);

namespace Piskvor\Test;

use PHPUnit\Framework\TestCase;
use Piskvor\BoardStateException;
use Piskvor\LifeBoard;
use Piskvor\TooManySpeciesException;

class LifeBoardTest extends TestCase
{
    /** @var LifeBoard */
    private $lb;

    protected function setUp()
    {
        $this->lb = new LifeBoard();
        $this->lb->setEdgeSize(20)
            ->setMaxIterations(20)
            ->setSpeciesCount(3);
    }

    public function testImportOrganism()
    {
        $this->lb->importOrganism(0, 0);
        $this->assertCount(1, $this->lb->getAllOrganisms());
        for ($x = 1; $x < 10; $x++) {
            $this->lb->importOrganism($x, 4);
            $this->assertCount($x + 1, $this->lb->getAllOrganisms());
        }
    }

    public function testImportInvalidCoords1()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->lb->importOrganism(-1, 0);
    }

    public function testImportInvalidCoords2()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->lb->importOrganism(5, 30);
    }

    public function testImportInvalidCoords3()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->lb->importOrganism('a', 30);
    }

    public function testImportTooManyTypes()
    {
        $this->lb->importOrganism(1, 0, 'a');
        $this->lb->importOrganism(5, 0, 'b');
        $this->lb->importOrganism(2, 0, 'c');
        $this->expectException(TooManySpeciesException::class);
        $this->lb->importOrganism(2, 0, 'd');
    }

    public function testImportBadWorld1()
    {
        $lb = new LifeBoard();
        $this->expectException(\InvalidArgumentException::class);
        $lb->setEdgeSize(0);
    }

    public function testImportBadWorld2()
    {
        $lb = new LifeBoard();
        $this->expectException(\InvalidArgumentException::class);
        $lb->setMaxIterations(-10);
    }

    public function testImportBadWorld3()
    {
        $lb = new LifeBoard();
        $this->expectException(\InvalidArgumentException::class);
        $lb->setSpeciesCount(0);
    }

    public function testImportOrganismBeforeWorld()
    {
        $lb = new LifeBoard();
        $lb->setMaxIterations(10);
        $this->expectException(BoardStateException::class);
        $lb->importOrganism(2, 2, 'x');
    }

    public function testNextGeneration012()
    {
        // no neighbors, die
        $this->lb->importOrganism(5, 5);
        $lbNext = $this->lb->nextGeneration();
        $this->assertCount(0, $lbNext->getAllOrganisms());

        // one neighbor, die
        $this->lb->importOrganism(5, 6);
        $lbNext2 = $this->lb->nextGeneration();
        $this->assertCount(0, $lbNext2->getAllOrganisms());

        // two neighbors, keep (and oscillate)
        $this->lb->importOrganism(5, 7);
        $lbNext3 = $this->lb->nextGeneration();
        $this->assertCount(3, $lbNext3->getAllOrganisms());

    }

    public function testNextGeneration3()
    {
        // three neighbors, make fourth
        $this->lb->importOrganism(5, 5);
        $this->lb->importOrganism(5, 6);
        $this->lb->importOrganism(6, 5);
        $lbNext3 = $this->lb->nextGeneration();
        $this->assertCount(4, $lbNext3->getAllOrganisms());
    }

    public function testNextGeneration4()
    {
        // here we check for liveness of [5,5] specifically - previous tests were checking for known and expected total counts
        $this->lb->importOrganism(5, 5);
        for ($y = 4; $y <= 6; $y++) {
            $this->lb->importOrganism(4, $y);
        }
        $this->lb->importOrganism(5, 6);
        echo $this->lb;
        $lbNext = $this->lb->nextGeneration();
        echo $lbNext;
        $this->assertFalse($lbNext->isLive(5, 5));
    }

    public function testNextGeneration5()
    {
        // here we check for liveness of [5,5] specifically - previous tests were checking for known and expected total counts
        $this->lb->importOrganism(5, 5);
        for ($y = 4; $y <= 6; $y++) {
            $this->lb->importOrganism(4, $y);
        }
        $this->lb->importOrganism(5, 6);
        $this->lb->importOrganism(5, 4);
        $lbNext = $this->lb->nextGeneration();
        $this->assertFalse($lbNext->isLive(5, 5));
    }

    public function testNextGeneration6()
    {
        // here we check for liveness of [5,5] specifically - previous tests were checking for known and expected total counts
        $this->lb->importOrganism(5, 5);
        for ($y = 4; $y <= 6; $y++) {
            $this->lb->importOrganism(4, $y);
        }
        $this->lb->importOrganism(5, 6);
        $this->lb->importOrganism(5, 4);
        $this->lb->importOrganism(6, 4);
        $lbNext = $this->lb->nextGeneration();
        $this->assertFalse($lbNext->isLive(5, 5));
    }

    public function testNextGeneration7()
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
        $lbNext = $this->lb->nextGeneration();
        $this->assertFalse($lbNext->isLive(5, 5));
    }

    public function testNextGeneration8()
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
        $lbNext = $this->lb->nextGeneration();
        $this->assertFalse($lbNext->isLive(5, 5));
    }

    public function testConflictingNewCells()
    {
        // here we check for liveness of [5,5] specifically - previous tests were checking for known and expected total counts
        for ($y = 4; $y <= 6; $y++) {
            $this->lb->importOrganism(4, $y, 'x');
            $this->lb->importOrganism(6, $y, 'O');
        }
        $tries = 1000;
        $result = 0;
        for ($t = $tries; $t >= 0; $t--) {
            $lbNext = $this->lb->nextGeneration();
            if ($lbNext->getOrganismNameByNumber($lbNext->getOrganism(5, 5)) === 'x') {
                $result++;
            }
        }
        $result = $result / $tries;
        //echo $result;
        // if the selection is random, we should be very close to 0.5 here
        $this->assertTrue($result > 0.49 && $result < 0.51);
    }
}