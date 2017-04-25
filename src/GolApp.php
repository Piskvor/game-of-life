<?php

namespace Piskvor;

use Hobnob\XmlStreamReader\Parser;

/**
 * Class GolApp - basic multi-species Game of Life simulator
 * @package Piskvor
 */
class GolApp
{
    /** @var string - path to input file */
    private $inFile;

    /** @var string - path to output file */
    private $outfile = 'out.xml';

    /** @var LifeBoard - the current board generation */
    private $board;

    /**
     * GolApp constructor.
     * @param string $inFile input XML file
     */
    public function __construct($inFile)
    {
        if (!file_exists($inFile)) {
            throw new \InvalidArgumentException('Invalid file specified');
        }
        $this->inFile = $inFile;

        $this->board = new LifeBoard(); // start with an empty board
    }

    public function run()
    {
        if ($this->parseFile()) {
            while ($newBoard = $this->board->nextGeneration()) {
                echo 'Generation: ', $this->board->getGeneration(), "\n";
                /*
                 echo 'Generation: ', $this->board->getGeneration(), "\n";
                 echo($this->board->getStringMap());
                // */
                $this->board = $newBoard;
            }
            $this->exportFile($this->board);
        } else {
            throw new \ErrorException('Unable to import file');
        }
    }

    /**
     * Go through the XML file and import it into the board
     */
    private function parseFile()
    {

        $xmlParser = new Parser(); // we have no idea how large the file is, try not to run out of memory

        // configuration options
        /** @noinspection PhpUnusedParameterInspection */
        $xmlParser->registerCallback(
            '/life/world',
            function (Parser $parser, \SimpleXMLElement $worldNode) {
                $this->board->setEdgeSize((int)$worldNode->cells);
                $this->board->setSpeciesCount((int)$worldNode->species);
                $this->board->setMaxIterations((int)$worldNode->iterations);
            }
        );

        // organism options
        /** @noinspection PhpUnusedParameterInspection */
        $xmlParser->registerCallback(
            '/life/organisms/organism',
            function (Parser $parser, \SimpleXMLElement $organismNode) {
                // @fixme: invalid x_pos and y_pos will become 0. Caveat: parsing speed?
                $x = (int)$organismNode->x_pos;
                $y = (int)$organismNode->y_pos;
                $organism = (string)$organismNode->species;
                $this->board->importOrganism($x, $y, $organism);
            }
        );

        return $xmlParser->parse(fopen($this->inFile, 'r'));

    }

    /**
     * @param LifeBoard $board
     */
    private function exportFile($board)
    {
        $writer = new \XMLWriter();
        $writer->openURI($this->outfile);
        $writer->startDocument('1.0', 'UTF-8');
        $writer->setIndent(4);
        $writer->startElement('life');
        $writer->startElement('world');
        $writer->writeElement('cells', $board->getEdgeSize());
        $writer->writeElement('species', $board->getSpeciesCount());
        $writer->writeElement('iterations', $board->getMaxIterations());
        $writer->endElement();
        $writer->startElement('organisms');
        $organisms = $board->getAllOrganisms();
        unset($board);
        foreach ($organisms as $id => $organism) {
            $writer->startElement('organism');
            $writer->writeElement('x_pos', $organism['x']);
            $writer->writeElement('y_pos', $organism['y']);
            $writer->writeElement('species', $organism['species']);
            $writer->endElement();
            unset($organisms[$id]);
        }
        $writer->endElement();
        $writer->endElement();
        $writer->endDocument();
        $writer->flush();
    }
}