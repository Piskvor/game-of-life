<?php
declare(strict_types=1);

namespace Piskvor;

use Piskvor\Exception\BoardStateException;
use Piskvor\Exception\TooManySpeciesException;
use Piskvor\Export\Exportable;
use Piskvor\Export\XmlExporter;

/**
 * Class LifeBoard represents a board at a given generation. Note that we're internally representing species as integers, and only convert from/to strings on import/export.
 * @package Piskvor
 */
class LifeBoard implements Exportable
{
    use XmlExporter;

    /** @var bool true if all necessary parameters have been passed (world size, number of species, number of generations), false otherwise */
    private $initialized = false;

    /** @var int current generation. 0 for the initial import, incremented by 1 for every generation */
    private $generation;

    /** @var int number of cells per row and column (i.e. 10 means 10 rows x 10 cols = 100 cells; note that the indexes are 0-based */
    private $edgeSize;

    /** @var int number of species to inhabit the board
     * @todo could be determined dynamically from data?
     */
    private $speciesCount = 0;

    /** @var int maximum number of iterations to go through */
    private $maxIterations = 0;

    /** @var int[][] a matrix of organisms currently live at the board */
    private $organisms = array();

    /** @var int[] mapping string=>int: species "name" (a character?) to internal representation */
    private $speciesNameMap;
    /** @var string[] the inverse of $speciesNameMap: given the internal species ID, what is the species "name" */
    private $speciesNumberMap;

    /** @var bool a simple optimization: if this becomes true, the pattern is static and won't change any more
     * (e.g. a 2x2 square)
     * - do not recompute, just keep bumping generations */
    private $stillLife = false;

    /**
     * @var bool
     */
    private $finished = false;

    /**
     * @return bool returns true if all the generations for the board have been completed; false if there are still generations left
     */
    public function isFinished(): bool
    {
        return $this->finished;
    }

    public function __construct(array $speciesNameMap = null, int $generation = 0)
    {
        if (is_array($speciesNameMap)) { // do not regenerate
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
     * @return bool true if we reached a static board
     */
    public function isStillLife(): bool
    {
        return $this->stillLife;
    }

    /**
     * @param bool $isStillLife - if true, the board is static and there's no point in recalculating it
     * @return LifeBoard
     */
    public function setStillLife(bool $isStillLife): LifeBoard
    {
        $this->stillLife = $isStillLife;
        return $this;
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

    /**
     * Get all the organisms in a flat array
     * @return array
     */
    public function getAllOrganisms(): array
    {
        $results = array();
        foreach ($this->organisms as $xPos => $cols) {
            foreach ($cols as $yPos => $species) {
                $results[] = array(
                    'x' => $xPos,
                    'y' => $yPos,
                    'species' => $this->getSpeciesNameById($species)
                );
            }
        }
        return $results;
    }

    /**
     * Sets a cell with a given type of organism. If conflict, choose one.
     * @param int $xPos
     * @param int $yPos
     * @param int $speciesId
     * @return int the ID of the species that's set (not necessarily what's passed in!)
     */
    public function setOrganism(int $xPos, int $yPos, int $speciesId): int
    {
        if (!isset($this->organisms[$xPos])) {
            // nothing in any x_pos yet, set up
            $this->organisms[$xPos] = array();
        }

        if (isset($this->organisms[$xPos][$yPos])) {
            // already occupied - find who survives: the previous
            $speciesId = $this->findSurvivor($this->organisms[$xPos][$yPos], $speciesId);
        }
        $this->organisms[$xPos][$yPos] = $speciesId;
        return $speciesId;
    }

    /**
     * Clears the cell unconditionally
     * @param $xPos
     * @param $yPos
     * @return int
     */
    public function clearOrganism(int $xPos, int $yPos)
    {
        if (isset($this->organisms[$xPos], $this->organisms[$xPos][$yPos])) {
            unset($this->organisms[$xPos][$yPos]);
        }
        return 0;
    }

    /**
     * Clears the cell if occupied by this type of organism
     * @param int $xPos
     * @param int $yPos
     * @param int $speciesId
     */
    public function clearOrganismType(int $xPos, int $yPos, int $speciesId)
    {
        if (isset($this->organisms[$xPos], $this->organisms[$xPos][$yPos]) && $this->organisms[$xPos][$yPos] == $speciesId) {
            unset($this->organisms[$xPos][$yPos]);
        }
    }

    /**
     * @return int
     */
    public function getMaxIterations(): int
    {
        return $this->maxIterations;
    }

    /**
     * @param int $maxIterations
     * @return LifeBoard
     */
    public function setMaxIterations(int $maxIterations): LifeBoard
    {
        if ($maxIterations <= 0) {
            throw new \InvalidArgumentException('Iterations must be >0');
        }

        $this->maxIterations = $maxIterations;
        $this->initializeBoard();
        return $this;
    }

    /**
     * @return bool true if all necessary data has been set
     */
    public function isInitialized(): bool
    {
        return $this->initialized;
    }

    /**
     * @return LifeBoard
     */
    private function initializeBoard(): LifeBoard
    {
        $this->initialized = ($this->maxIterations > 0) && ($this->edgeSize > 0) && ($this->speciesCount > 0);
        /*
         * @todo perhaps check if this would be faster than creating at access time?
         * @see $this->setOrganism()
        if ($this->initialized) {
            for ($x = $this->getEdgeSize() - 1; $x >= 0; $x--) {
                $this->organisms[$x] = array();
            }
        }
        */
        return $this;
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
     * @return LifeBoard
     */
    public function setEdgeSize(int $edgeSize): LifeBoard
    {
        if ($edgeSize <= 0) {
            throw new \InvalidArgumentException('Size must be >0');
        }
        $this->edgeSize = $edgeSize;
        $this->initializeBoard();
        return $this;
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
     * @return LifeBoard
     */
    public function setSpeciesCount(int $speciesCount): LifeBoard
    {
        if ($speciesCount <= 0) {
            throw new \InvalidArgumentException('Species count must be >0');
        }

        $this->speciesCount = $speciesCount;
        $this->initializeBoard();
        return $this;
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
     * @param int $xPos
     * @param int $yPos
     * @param string $speciesName
     * @return int
     * @throws BoardStateException
     */
    public function importOrganism(int $xPos, int $yPos, string $speciesName = 'o'): int
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
     * @return \int[][]
     */
    public function getOrganisms(): array
    {
        return $this->organisms;
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
     * Returns a new LifeBoard with the state of the next generation.
     * NOTE that we are NOT changing state of $this, we are making a new instance for the new state.
     * (Except in the degenerate case of still lives, where we don't compute anything, just bump the generation counter up)
     * @return LifeBoard
     */
    public function getNextBoard(): LifeBoard
    {
        if ($this->getMaxIterations() <= $this->generation) {
            $this->finished = true;
            return $this;
        }
        if ($this->isStillLife()) { // nothing more to do here
            $this->generation++;
            return $this;
        } else {
            $newBoard = new LifeBoard($this->speciesNameMap, $this->generation + 1);
            $newBoard->setSpeciesCount($this->getSpeciesCount())
                ->setEdgeSize($this->getEdgeSize())
                ->setMaxIterations($this->getMaxIterations());
            $newBoard->calculateNewGeneration($this);
            if ($newBoard->equals($this)) {
                $newBoard->setStillLife(true);
            }
            return $newBoard;
        }
    }

    /**
     * Actual calculation happens here. We're using the most naive and sloooooow O(c*c*s) method here.
     * Possible optimizations: check for still life, check for loops, ignore empty areas, ignore already processed cells and only check once per point. Or go for HashLife, currently most efficient.
     * @param LifeBoard $oldBoard
     */
    private function calculateNewGeneration(LifeBoard $oldBoard)
    {
        for ($species = $this->getSpeciesCount(); $species > 0; $species--) {
            for ($x = $this->edgeSize - 1; $x >= 0; $x--) {
                for ($y = $this->edgeSize - 1; $y >= 0; $y--) {
                    $liveNeighbors = $oldBoard->countLiveNeighbors($x, $y, $species);
                    switch ($liveNeighbors) {
                        case 0:
                        case 1:
                            $this->clearOrganismType($x, $y, $species);
                            break;
                        case 2:
                            if ($oldBoard->getOrganism($x, $y) == $species) {
                                $this->setOrganism($x, $y, $species);
                            } // else no-op
                            break;
                        case 3:
                            $this->setOrganism($x, $y, $species);
                            break;
                        default:
                            $this->clearOrganismType($x, $y, $species);
                    }
                }
            }
        }
    }

    /**
     * Calculates live neighbors for the given cell.
     * @TODO This could be optimized four ways from Friday.
     * @param int $x
     * @param int $y
     * @param int $species
     * @return int
     */
    public function countLiveNeighbors(int $x, int $y, int $species): int
    {
        $liveNeighbors = 0;
        for ($xx = -1; $xx <= 1; $xx++) {
            for ($yy = -1; $yy <= 1; $yy += 2) {
                if ($this->getOrganism($x + $xx, $y + $yy) == $species) {
                    $liveNeighbors++;
                }
            }
        }
        for ($xx = -1; $xx <= 1; $xx += 2) {
            if ($this->getOrganism($x + $xx, $y) == $species) {
                $liveNeighbors++;
            }
        }
        return $liveNeighbors;
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

}