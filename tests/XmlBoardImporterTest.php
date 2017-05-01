<?php
declare(strict_types=1);

namespace Piskvor\Test;

use PHPUnit\Framework\TestCase;
use Piskvor\Import\XmlBoardImporter;
use Piskvor\LifeBoard;

class XmlBoardImporterTest extends TestCase
{

    /**
     * Test that we won't import a file which is not a well-formed XML file.
     * Note that we *could* validate against the DTD (@see ../life.dtd )
     * but we're just happy that we get something that matches the expected structure
     * and we skip any extraneous tags.
     */
    public function testImportInvalidXml()
    {
        $xbi = new XmlBoardImporter(__DIR__ . '/imports/invalid-xml.xml', new LifeBoard());
        $this->expectException(\Exception::class);
        // do you even XML
        $xbi->parseFile();
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
        $xbi = new XmlBoardImporter(__DIR__ . '/imports/no-world.xml', new LifeBoard());
        $xbi->parseFile();
        // /life/world is missing from XML
        $this->assertFalse($xbi->getBoard()->isInitialized());
    }

    /**
     * Test that we don't get tripped up by extra tags that we don't care about.
     * @TODO: Perhaps we *should* worry about those, as we might be
     * getting something that's not intended for our consumption?
     */
    public function testImportExtraneous()
    {
        $xbi = new XmlBoardImporter(__DIR__ . '/imports/extraneous.xml', new LifeBoard());
        $xbi->parseFile();
        $lb = $xbi->getBoard();
        // extra XML elements are ignored, the board is initialized
        $this->assertTrue($lb->isInitialized());
        // there's nothing on the board
        $this->assertCount(0, $lb->getOrganismList());
    }


    /**
     * Test that we *do* import multiple organisms,
     * even though they replace each other in a single cell.
     * @TODO: perhaps reject the import - is this a sign of insane data?
     */
    public function testConflictingCellsImport()
    {
        $xbi = new XmlBoardImporter(__DIR__ . '/imports/conflict.xml', new LifeBoard());
        $xbi->parseFile();
        $lb = $xbi->getBoard();
        $this->assertTrue($lb->isLive(10, 0));
        $this->assertCount(1, $lb->getOrganismList());
    }

}
