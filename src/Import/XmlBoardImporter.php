<?php
declare(strict_types=1);

namespace Piskvor\Import;

use Hobnob\XmlStreamReader\Parser;
use Piskvor\LifeBoard;

class XmlBoardImporter
{

    /** @var string - path to input file */
    private $inFile;

    /** @var LifeBoard - the current board generation */
    private $board;


    /**
     * XmlBoardImporter constructor.
     * @param string $inFile
     * @param LifeBoard $board
     */
    public function __construct(string $inFile, LifeBoard $board)
    {
        $this->inFile = $inFile;
        $this->board = $board;
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
                $this->board->setMaxGenerations((int)$worldNode->iterations);
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
                $this->board->addOrganism($xPos, $yPos, $speciesName);
                $organismCount++;
            }
        );

        $parser = $xmlParser->parse(fopen($this->inFile, 'r'));

        // do not recalculate empty board
        if ($organismCount === 0) {
            $this->board->setStillLife(true);
        }
        return $parser;

    }

    public function getBoard(): LifeBoard {
        return $this->board;
    }
}