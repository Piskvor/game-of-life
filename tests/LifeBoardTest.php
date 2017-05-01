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
    /** @var Log|\Psr\Log\LoggerInterface */
    private $log;

    /** @var LifeBoard default that's set up for a majority of the tests */
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
        $this->lb->addOrganism(0, 0);
        $this->assertCount(1, $this->lb->getOrganismList());
        for ($x = 1; $x < 10; $x++) {
            $this->lb->addOrganism($x, 4);
            $this->assertCount($x + 1, $this->lb->getOrganismList());
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
        $this->lb->addOrganism(0, 0);
        $x++;

        $this->assertCount(1, $this->lb->getOrganismList());
        // import +9 more
        for (; $x < 10; $x++) {
            // $x is both a counter and coordinate here
            $this->lb->addOrganism($x, 4);
        }

        // import +1 more _over_ an existing one
        $this->lb->addOrganism(5, 4);
        //$x++; // this MUST NOT bump the count!

        // there should now be 10 organisms
        $this->assertCount($x, $this->lb->getOrganismList());
    }

    /**
     * Test that it's not possible to import organisms
     * that are outside the board in negative space
     */
    public function testImportInvalidCoordsOutsideBoardNegative()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->lb->addOrganism(-1, 0);
    }

    /**
     * Test that it's not possible to import organisms
     * that are outside the board beyond its size
     */
    public function testImportInvalidCoordsOutsideBoardPositive()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->lb->addOrganism(5, 30);
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
        $this->lb->addOrganism(1, 0, 'a');
        $this->lb->addOrganism(5, 0, 'b');
        $this->lb->addOrganism(2, 0, 'c');
        $this->expectException(TooManySpeciesException::class);
        $this->lb->addOrganism(2, 0, 'd');
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
        $lb->addOrganism(2, 2, 'x');
    }

    /**
     * Test that creating a board programmatically
     * is equivalent to importing it from XML.
     *
     * This might help us with creating further importers later,
     * should the need for other file formats arise.
     * @link http://www.mirekw.com/ca/ca_files_formats.html
     */
    public function testOutsideSourceExport()
    {
        $compareFile = __DIR__ . '/imports/small-world.xml';
        $outFile = __DIR__ . '/../out.xml';
        // we are NOT parsing the file, just match its structure in code instead
        $lb = new LifeBoard();
        $lb->setEdgeSize(20);
        $lb->setSpeciesCount(3);
        $lb->setMaxGenerations(300);
        foreach ([10, 11] as $x) {
            foreach ([0, 1] as $y) {
                $lb->addOrganism($x, $y, 'a');
            }
        }
        $lb->addOrganism(2, 1, 't');
        $lb->addOrganism(3, 2, 't');
        foreach ([1, 2, 3] as $x) {
            $lb->addOrganism($x, 3, 't');
        }

        $lb->export($outFile);

        // see if coded and exported version matches
        $expected = new \DOMDocument();
        $expected->load($compareFile);
        $actual = new \DOMDocument();
        $actual->load($outFile);
        $this->assertEqualXMLStructure($expected->documentElement, $actual->documentElement);
    }

}