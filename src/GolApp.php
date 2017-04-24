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
                //*
                 echo 'Generation: ', $this->board->getGeneration(), "\n";
                 dump($this->board->getStringMap());
                // */
                $this->board = $newBoard;
            }
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
            '/life/world/cells',
            function (Parser $parser, \SimpleXMLElement $node) {
                $this->board->setEdgeSize((int)$node);
            }
        );
        /** @noinspection PhpUnusedParameterInspection */
        $xmlParser->registerCallback(
            '/life/world/species',
            function (Parser $parser, \SimpleXMLElement $node) {
                $this->board->setSpeciesCount((int)$node);
            }
        );
        /** @noinspection PhpUnusedParameterInspection */
        $xmlParser->registerCallback(
            '/life/world/iterations',
            function (Parser $parser, \SimpleXMLElement $node) {
                $this->board->setMaxIterations((int)$node);
            }
        );

        // organism options
        /** @noinspection PhpUnusedParameterInspection */
        $xmlParser->registerCallback(
            '/life/organisms/organism',
            function (Parser $parser, \SimpleXMLElement $node) {
                // @fixme: invalid x_pos and y_pos will become 0. Caveat: parsing speed?
                $x = (int)$node->x_pos;
                $y = (int)$node->y_pos;
                $organism = (string)$node->species;
                $this->board->importOrganism($x, $y, $organism);

            }
        );

        return $xmlParser->parse(fopen($this->inFile, 'r'));

    }
}