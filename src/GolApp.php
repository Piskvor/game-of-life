<?php
declare(strict_types=1);

namespace Piskvor;

use Hobnob\XmlStreamReader\Parser;
use Psr\Log\LogLevel;

/**
 * Class GolApp - basic multi-species Game of Life simulator
 * @package Piskvor
 */
class GolApp
{
    /** @var bool if true, board is shown for each generation */
    private $isDebug = true;
    /** @var string - path to input file */
    private $inFile;
    /** @var string - path to output file */
    private $outFile;

    /** @var LifeBoard - the current board generation */
    private $board;

    /** @var Log */
    private $log;

    /**
     * GolApp constructor.
     * @param string $inFile input XML file
     * @param string $outFile
     */
    public function __construct($inFile, $outFile = 'out.xml')
    {
        $this->log = new Log();
        if (!(is_file($inFile) || is_link($inFile)) || !is_readable($inFile)) {
            throw new \InvalidArgumentException('Invalid file specified: ' . $inFile);
        }
        $this->inFile = $inFile;
        $this->outFile = $outFile;
        if ($this->isDebug) {
            $this->log->setLevel(LogLevel::DEBUG);
            $this->log->debug($this->inFile . "\n");
        }
        $this->board = new LifeBoard(); // start with an empty board
    }

    /**
     * @return LifeBoard
     */
    public function getBoard(): LifeBoard
    {
        return $this->board;
    }

    /**
     * Processes the board and its generations
     * @throws \ErrorException
     */
    public function run()
    {
        if ($this->parseFile()) {
            while ($newBoard = $this->board->getNextBoard()) {

                $this->log->info('Generation: ' . $this->board->getGeneration() . "\n");
                if ($this->isDebug) { // might be expensive
                    $this->log->debug($this->board);
                }
                $this->board = $newBoard;
            }

            if ($this->board->export($this->outFile)) {
                $this->log->info('Exported to ' . $this->outFile . "\n");
            } else {
                throw new \ErrorException('Unable to export file');
            }
        } else {
            throw new \ErrorException('Unable to import file');
        }
    }

    /**
     * Go through the XML file and import it into the board
     */
    public function parseFile(): Parser
    {

        $xmlParser = new Parser(); // we have no idea how large the file is, try not to run out of memory
        $organismCount = 0;

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
            function (Parser $parser, \SimpleXMLElement $organismNode) use (&$organismCount) {
                // @fixme: invalid x_pos and y_pos will become 0. Caveat: parsing speed?
                $xPos = (int)$organismNode->x_pos;
                $yPos = (int)$organismNode->y_pos;
                $speciesName = (string)$organismNode->species;
                $this->board->importOrganism($xPos, $yPos, $speciesName);
                $organismCount++;
            }
        );

        $parser = $xmlParser->parse(fopen($this->inFile, 'r'));

        // do not recalculate empty board
        if ($organismCount === 0) {
            $this->board->setStillLife(true);
        }
        if ($this->isDebug) {
            $this->log->debug($this->board);
        }
        return $parser;

    }

}