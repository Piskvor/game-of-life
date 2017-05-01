<?php
declare(strict_types=1);

namespace Piskvor;

use Hobnob\XmlStreamReader\Parser;
use Piskvor\Import\XmlBoardImporter;
use Piskvor\LivenessCalculator\LifeBoardCalculator;
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
     * GolApp constructor: set up logging and verify basic sanity.
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
            if ($this->isDebug) {
                $this->log->debug((string)$this->board);
            }

            $calculator = new LifeBoardCalculator();
            while (!$this->board->isFinished()) {
                $newBoard = $calculator->getNextBoard($this->board);
                $this->log->info('Generation: ' . $this->board->getGeneration() . "\n");
                if ($this->isDebug) { // might be expensive
                    $this->log->debug((string)$this->board);
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

    public function parseFile(): Parser
    {
        $importer = new XmlBoardImporter($this->inFile, $this->board);
        return $importer->parseFile();
    }

}