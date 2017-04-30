<?php
declare(strict_types=1);

namespace Piskvor\Test;

use PHPUnit\Framework\TestCase;
use Piskvor\GolApp;
use Piskvor\LifeBoard;

class GolAppTest extends TestCase
{
    public function testCannotImportNonFile()
    {
        $this->expectException(\InvalidArgumentException::class);
        // no importing directories
        new GolApp(__DIR__);
    }

    public function testImportInvalidXml()
    {
        $ga = new GolApp(__DIR__ . '/invalid-xml.xml');
        $this->expectException(\Exception::class);
        // do you even XML
        $ga->parseFile();
    }

    public function testImportNoWorld()
    {
        $ga = new GolApp(__DIR__ . '/no-world.xml');
        $ga->parseFile();
        // /life/world is missing from XML
        $this->assertFalse($ga->getBoard()->isInitialized());
    }

    public function testImportExtraneous()
    {
        $ga = new GolApp(__DIR__ . '/extraneous.xml');
        $ga->parseFile();
        $lb = $ga->getBoard();
        // extra XML elements are ignored, the board is initialized
        $this->assertTrue($lb->isInitialized());
        // there's nothing on the board
        $this->assertCount(0, $lb->getAllOrganisms());
    }

    public function testImportExport()
    {
        $infile = __DIR__ . '/small-world.xml';
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

    public function testOutsideSourceExport()
    {
        $compareFile = __DIR__ . '/small-world.xml';
        $outFile = __DIR__ . '/../out.xml';
        // we are NOT parsing the file, just match its structure in code instead
        $lb = new LifeBoard();
        $lb->setEdgeSize(20)
            ->setSpeciesCount(3)
            ->setMaxIterations(300);
        foreach ([10, 11] as $x) {
            foreach ([0, 1] as $y) {
                $lb->importOrganism($x, $y, 'a');
            }
        }
        $lb->importOrganism(2, 1, 't');
        $lb->importOrganism(3, 2, 't');
        foreach ([1, 2, 3] as $x) {
            $lb->importOrganism($x, 3, 't');
        }

        $lb->export($outFile);

        // see if coded and exported version matches
        $expected = new \DOMDocument();
        $expected->load($compareFile);
        $actual = new \DOMDocument();
        $actual->load($outFile);
        $this->assertEqualXMLStructure($expected->documentElement, $actual->documentElement);
    }


    public function testConflictingCellsImport()
    {
        $ga = new GolApp(__DIR__ . '/conflict.xml');
        $ga->parseFile();
        $lb = $ga->getBoard();
        $this->assertTrue($lb->isLive(10, 0));
        $this->assertCount(1, $lb->getAllOrganisms());
    }


}
