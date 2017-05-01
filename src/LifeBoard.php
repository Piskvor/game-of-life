<?php
declare(strict_types=1);

namespace Piskvor;

use Piskvor\Exception\BoardStateException;
use Piskvor\Exception\TooManySpeciesException;
use Piskvor\Export\Exportable;
use Piskvor\Export\XmlExporter;

/**
 * Class LifeBoard represents a board at a given generation. Note that we're internally representing species as integers, and only convert from/to strings on import/export.
 *
 * The essential flow is:
 * addOrganism()
 *  if species not in list yet, give it an autoincremented ID
 * if not isFinished() {
 *  getNextBoard()
 *   calculateNewGeneration()
 *    countLiveNeighbors()
 *    if <2 then die
 *    if =2 then no change[*1]
 *    if =3 then reproduce here[*1]
 *    if >3 then die
 *    [*1]: check for conflict with other species
 * }
 *
 * @package Piskvor
 */
class LifeBoard implements LifeBoardPublishable, Exportable
{
    use XmlExporter;

    // initial world settings
    /** @var int number of cells per row and column (i.e. 10 means 10 rows x 10 cols = 100 cells; note that the indexes are 0-based */
    private $edgeSize;
    /**
     * @var int number of species to inhabit the board
     * @todo could be determined dynamically from data?
     */
    private $speciesCount = 0;
    /** @var int maximum number of iterations to go through */
    private $maxGenerations = 0;

    // board global state
    /** @var bool true if all necessary parameters have been passed (world size, number of species, number of generations), false otherwise */
    private $initialized = false;
    /** @var bool if true, all the requested generations were run and this is the final state */
    private $finished = false;
    /** @var int current generation. 0 for the initial import, incremented by 1 for every generation */
    private $generation;

    // state of non-empty cells
    /** @var int[][] a matrix of organisms currently live at the board */
    private $organisms = array();

    // internal variables
    /** @var bool a simple optimization: if this becomes true, the pattern is static and won't change any more
     * (e.g. a 2x2 square)
     * - do not recompute, just keep bumping generations */
    private $stillLife = false;
    /** @var int[] mapping string=>int: species "name" (a character?) to internal representation: autoincremented integer, with 0 meaning "no organism" */
    private $speciesNameMap;
    /** @var string[] the inverse of $speciesNameMap: given the internal species ID, what is the species "name" */
    private $speciesNumberMap;

    /**
     * LifeBoard constructor: set basic data.
     * @param array|null $speciesNameMap
     * @param int $generation
     */
    public function __construct(array $speciesNameMap = null, int $generation = 0)
    {
        if (is_array($speciesNameMap)) { // do not regenerate, receive this from previous board
            $this->speciesNameMap = $speciesNameMap;
        } else { // just start with empty
            $this->speciesNameMap = array();
        }
        if ($generation > 0) {
            $this->generation = $generation;
        } else {
            $this->generation = 0;
        }
    }

    /**
     * Add a new organism into the board.
     * @param int $xPos
     * @param int $yPos
     * @param string $speciesName
     * @return int
     * @throws BoardStateException
     */
    public function addOrganism(int $xPos, int $yPos, string $speciesName = 'o'): int
    {
        if ($this->isInitialized()) {
            if ($xPos >= $this->getEdgeSize() || $yPos >= $this->getEdgeSize() || $xPos < 0 || $yPos < 0) {
                throw new \InvalidArgumentException('Cell outside board!');
            }
            $organismNumber = $this->getIdBySpeciesName($speciesName);
            return $this->setOrganism($xPos, $yPos, $organismNumber);
        } else {
            throw new BoardStateException('Board not configured yet!');
        }
    }


    /**
     * Get all the organisms in a flat array
     * @return array
     */
    public function getOrganismList(): array
    {
        $results = array();
        foreach ($this->organisms as $xPos => $cols) {
            foreach ($cols as $yPos => $species) {
                $results[] = array(
                    'x_pos' => $xPos,
                    'y_pos' => $yPos,
                    'species' => $this->getSpeciesNameById($species)
                );
            }
        }
        return $results;
    }

    /**
     * Inefficient, but workable: we generate the board as a string representation,
     * and strings are inherently comparable.
     * Also, this makes our boards easier to debug visually.
     * @param LifeBoard $lb
     * @return bool
     */
    public function equals(LifeBoard $lb): bool
    {
        return (string)$this === (string)$lb;
    }

    /**
     * Get a string representation of the map *state* - regardless of generation count
     * @return string
     */
    public function __toString(): string
    {
        $string = '';
        for ($y = $this->edgeSize - 1; $y >= 0; $y--) {
            for ($x = $this->edgeSize - 1; $x >= 0; $x--) {
                $string .= $this->getSpeciesNameById($this->getOrganism($x, $y));
            }
            $string .= "\n";
        }
        return $string . "\n";
    }

    /**
     * @return bool true if we reached a static board
     */
    public function isStillLife(): bool
    {
        return $this->stillLife;
    }

    /**
     * @param bool $isStillLife - if true, the board is static and there's no point in recalculating it
     */
    public function setStillLife(bool $isStillLife)
    {
        $this->stillLife = $isStillLife;
    }

    /**
     * Return the ID of a species in the given cell (0 for "dead cell")
     * @param int $xPos
     * @param int $yPos
     * @return int the numerical ID of the species
     */
    public function getOrganism(int $xPos, int $yPos): int
    {
        if (!isset($this->organisms[$xPos]) || !isset($this->organisms[$xPos][$yPos])) {
            return 0;
        } else {
            return $this->organisms[$xPos][$yPos];
        }
    }

    /**
     * Returns true if the cell contains any living organism (regardless of species), false otherwise
     * @param int $xPos
     * @param int $yPos
     * @return bool
     */
    public function isLive(int $xPos, int $yPos): bool
    {
        return $this->getOrganism($xPos, $yPos) > 0;
    }

    public function setFinished(bool $finished)
    {
        $this->finished = $finished;
    }

    public function incrementGeneration()
    {
        $this->generation++;
    }


    /**
     * Sets a cell with a given species. If conflict, choose one.
     * @param int $xPos
     * @param int $yPos
     * @param int $speciesId
     * @return int the ID of the species that's set (not necessarily what's passed in!)
     */
    public function setOrganism(int $xPos, int $yPos, int $speciesId): int
    {
        if (!isset($this->organisms[$xPos])) {
            // nothing in this x_pos yet, set up
            $this->organisms[$xPos] = array();
        }

        if (isset($this->organisms[$xPos][$yPos])) {
            // already occupied - find who survives: the previous or the new
            $speciesId = $this->findSurvivor($this->organisms[$xPos][$yPos], $speciesId);
        }
        $this->organisms[$xPos][$yPos] = $speciesId;
        return $speciesId;
    }

    /**
     * Used for passing species name map to new board
     * @return array|\int[]
     */
    public function getSpeciesNameMap()
    {
        return $this->speciesNameMap;
    }

    /**
     * @param int $originalOrganism
     * @param int $newOrganism
     * @return int the ID of the surviving organism
     */
    private function findSurvivor(int $originalOrganism, int $newOrganism): int
    {
        // @todo perhaps mt_rand() is overkill, but rand() doesn't quite guarantee a fair coin toss
        return (mt_rand(0, 1) > 0) ? $originalOrganism : $newOrganism;
    }

    /**
     * Clears the cell if occupied by this species, else noop
     * @param int $xPos
     * @param int $yPos
     * @param int $speciesId
     */
    public function clearOrganismSpecies(int $xPos, int $yPos, int $speciesId)
    {
        if (isset($this->organisms[$xPos], $this->organisms[$xPos][$yPos]) && $this->organisms[$xPos][$yPos] == $speciesId) {
            unset($this->organisms[$xPos][$yPos]);
        }
    }

    /**
     * @return int number of
     */
    public function getMaxGenerations(): int
    {
        return $this->maxGenerations;
    }

    /**
     * @param int $maxGenerations
     */
    public function setMaxGenerations(int $maxGenerations)
    {
        if ($maxGenerations <= 0) {
            throw new \InvalidArgumentException('Iterations must be >0');
        }

        $this->maxGenerations = $maxGenerations;
        $this->initializeBoard();
    }

    /**
     * @return bool true if all necessary data has been set
     */
    public function isInitialized(): bool
    {
        return $this->initialized;
    }

    /**
     * Checks if the board is ready for calculations yet, or even if cells may be populated.
     * @return bool true if all necessary data has been set
     */
    private function initializeBoard(): bool
    {
        $this->initialized = ($this->maxGenerations > 0) && ($this->edgeSize > 0) && ($this->speciesCount > 0);
        /*
         * @todo perhaps check if this would be faster than creating at access time?
         * @see $this->setOrganism()
        if ($this->initialized) {
            for ($x = $this->getEdgeSize() - 1; $x >= 0; $x--) {
                $this->organisms[$x] = array();
            }
        }
        */
        return $this->initialized;
    }

    /**
     * @return int
     */
    public function getGeneration(): int
    {
        return $this->generation;
    }

    /**
     * @return int
     */
    public function getEdgeSize(): int
    {
        return $this->edgeSize;
    }

    /**
     * @param int $edgeSize
     */
    public function setEdgeSize(int $edgeSize)
    {
        if ($edgeSize <= 0) {
            throw new \InvalidArgumentException('Size must be >0');
        }
        $this->edgeSize = $edgeSize;
        $this->initializeBoard();
    }

    /**
     * @return int
     */
    public function getSpeciesCount(): int
    {
        return $this->speciesCount;
    }

    /**
     * @param int $speciesCount
     */
    public function setSpeciesCount(int $speciesCount)
    {
        if ($speciesCount <= 0) {
            throw new \InvalidArgumentException('Species count must be >0');
        }

        $this->speciesCount = $speciesCount;
        $this->initializeBoard();
    }

    /**
     * Map the string name of an organism into an internal representation
     * @param string $speciesName
     * @return int
     * @throws TooManySpeciesException
     */
    public function getIdBySpeciesName(string $speciesName): int
    {
        if (!isset($this->speciesNameMap[$speciesName])) {
            $nextCount = count($this->speciesNameMap) + 1;
            if ($nextCount <= $this->speciesCount) {
                $this->speciesNameMap[$speciesName] = $nextCount;
            } else {
                throw new TooManySpeciesException('Attempted to add too many species');
            }
        }
        return $this->speciesNameMap[$speciesName];
    }

    /**
     * Map the internal representation into a string
     * @param int $organismNumber
     * @return string
     */
    public function getSpeciesNameById(int $organismNumber): string
    {
        if (is_null($this->speciesNumberMap)) {
            $this->speciesNumberMap = array_flip($this->speciesNameMap);
        }
        if (!isset($this->speciesNumberMap[$organismNumber])) {
            // none
            return '.';
        }
        return $this->speciesNumberMap[$organismNumber];
    }

    /**
     * @return bool returns true if all the generations for the board have been completed; false if there are still generations left
     */
    public function isFinished(): bool
    {
        return $this->finished;
    }

}