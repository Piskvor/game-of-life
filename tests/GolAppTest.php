<?php
declare(strict_types=1);

namespace Piskvor\Test;

use PHPUnit\Framework\TestCase;
use Piskvor\GolApp;

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
        $ga = new GolApp(__DIR__ .'/invalid-xml.xml');
        $this->expectException(\Exception::class);
        // do you even XML
        $ga->parseFile();
    }

    public function testImportNoWorld()
    {
        $ga = new GolApp(__DIR__ .'/no-world.xml');
        $ga->parseFile();
        // /life/world is missing from XML
        $this->assertFalse($ga->getBoard()->isInitialized());
    }

    public function testImportExtraneous()
    {
        $ga = new GolApp(__DIR__ .'/extraneous.xml');
        $ga->parseFile();
        $lb = $ga->getBoard();
        // extra XML elements are ignored, the board is initialized
        $this->assertTrue($lb->isInitialized());
        // there's nothing on the board
        $this->assertCount(0,$lb->getAllOrganisms());
    }

}
