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
    private $outfile;

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
            throw new \InvalidArgumentException('Invalid file specified');
        }
        $this->inFile = $inFile;
        $this->outfile = $outFile;
        if ($this->isDebug) {
            $this->log->setLevel(LogLevel::DEBUG);
            $this->log->debug($this->inFile, "\n");
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
            while ($newBoard = $this->board->nextGeneration()) {

                $this->log->info('Generation: ' . $this->board->getGeneration() . "\n");
                if ($this->isDebug) { // might be expensive
                    $this->log->debug($this->board);
                }
                $this->board = $newBoard;
            }

            if ($this->exportFile($this->board, $this->outfile)) {
                $this->log->info('Exported to ' . $this->outfile . "\n");
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
                $x = (int)$organismNode->x_pos;
                $y = (int)$organismNode->y_pos;
                $organism = (string)$organismNode->species;
                $this->board->importOrganism($x, $y, $organism);
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

    /**
     * @param LifeBoard $board
     * @param string $filename
     * @return bool
     */
    public function exportFile($board, $filename = 'out.xml'): bool
    {
        $writer = new \XMLWriter();
        $writer->openURI($filename);
        $writer->startDocument('1.0', 'UTF-8');
        $writer->setIndent(true);
        $writer->startElement('life');
        $writer->startElement('world');
        $writer->writeElement('cells', (string)$board->getEdgeSize());
        $writer->writeElement('species', (string)$board->getSpeciesCount());
        $writer->writeElement('iterations', (string)$board->getMaxIterations());
        $writer->endElement();
        $writer->startElement('organisms');
        $organisms = $board->getAllOrganisms();
        unset($board);
        foreach ($organisms as $id => $organism) {
            $writer->startElement('organism');
            $writer->writeElement('x_pos', (string)$organism['x']);
            $writer->writeElement('y_pos', (string)$organism['y']);
            $writer->writeElement('species', $organism['species']);
            $writer->endElement();
            unset($organisms[$id]);
        }
        $writer->endElement();
        $writer->endElement();
        $result = $writer->endDocument();
        $writer->flush();
        return (bool)$result;
    }
}