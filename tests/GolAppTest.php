<?php
declare(strict_types=1);

namespace Piskvor\Test;

use PHPUnit\Framework\TestCase;
use Piskvor\GolApp;
use Piskvor\LifeBoard;

class GolAppTest extends TestCase
{
    /**
     * Test that we won't import something which is not a file: a directory
     */
    public function testCannotImportNonFile()
    {
        $this->expectException(\InvalidArgumentException::class);
        // no importing directories
        new GolApp(__DIR__);
    }

    /**
     * Test that importing and immediately exporting gives a result
     * that is equivalent to the original.
     * Note that XML element ordering is irrelevant
     * - we are only interested in board state.
     */
    public function testImportExport()
    {
        $infile = __DIR__ . '/imports/small-world.xml';
        $outFile = __DIR__ . '/../out.xml';
        $ga = new GolApp($infile, $outFile);
        $ga->parseFile();
        $ga->getBoard()->export($outFile);

        // see if imported and exported version matches
        $expected = new \DOMDocument();
        $expected->load($infile);
        $actual = new \DOMDocument();
        $actual->load($outFile);
        $this->assertEqualXMLStructure($expected->documentElement, $actual->documentElement);
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
