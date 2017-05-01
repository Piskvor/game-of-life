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
     * Test that we won't import a file which is not a well-formed XML file.
     * Note that we *could* validate against the DTD (@see ../life.dtd )
     * but we're just happy that we get something that matches the expected structure
     * and we skip any extraneous tags.
     */
    public function testImportInvalidXml()
    {
        $ga = new GolApp(__DIR__ . '/imports/invalid-xml.xml');
        $this->expectException(\Exception::class);
        // do you even XML
        $ga->parseFile();
    }

    /**
     * Test that importing a file without the /life/world preamble fails.
     * This would fail on generation step also (as not initialized).
     * Note that we don't care where in the XML file this block is,
     * only that it exists.
     * We call it "preamble" by convention only.
     */
    public function testImportNoWorld()
    {
        $ga = new GolApp(__DIR__ . '/imports/no-world.xml');
        $ga->parseFile();
        // /life/world is missing from XML
        $this->assertFalse($ga->getBoard()->isInitialized());
    }

    /**
     * Test that we don't get tripped up by extra tags that we don't care about.
     * @TODO: Perhaps we *should* worry about those, as we might be
     * getting something that's not intended for our consumption?
     */
    public function testImportExtraneous()
    {
        $ga = new GolApp(__DIR__ . '/imports/extraneous.xml');
        $ga->parseFile();
        $lb = $ga->getBoard();
        // extra XML elements are ignored, the board is initialized
        $this->assertTrue($lb->isInitialized());
        // there's nothing on the board
        $this->assertCount(0, $lb->getAllOrganisms());
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


    /**
     * Test that we *do* import multiple organisms,
     * even though they replace each other in a single cell.
     * @TODO: perhaps reject the import - is this a sign of insane data?
     */
    public function testConflictingCellsImport()
    {
        $ga = new GolApp(__DIR__ . '/imports/conflict.xml');
        $ga->parseFile();
        $lb = $ga->getBoard();
        $this->assertTrue($lb->isLive(10, 0));
        $this->assertCount(1, $lb->getAllOrganisms());
    }


}
