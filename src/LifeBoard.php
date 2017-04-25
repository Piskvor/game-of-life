<?php
declare(strict_types=1);

namespace Piskvor;


/**
 * Class LifeBoard represents a board at a given generation. Note that we're internally representing species as ints, and only convert from/to strings on import/export.
 * @package Piskvor
 */
class LifeBoard
{
    /** @var bool true if all necessary parameters have been passed, false otherwise */
    private $initialized = false;
    /** @var int current generation. 0 for the initial import, incremented by 1 for every generation */
    private $generation;

    /** @var int number of cells per row and column (i.e. 10 means 10 rows x 10 cols = 100 cells */
    private $edgeSize;

    /** @var int number of species to inhabit the board @todo could be determined dynamically from data? */
    private $speciesCount;

    /** @var int maximum number of iterations to go through */
    private $maxIterations = 0;

    /** @var int[][] a matrix of organisms */
    private $organisms = array();

    /** @var int[] mapping string=>int of organism to internal rep */
    private $organismNameMap;
    /** @var string[] the inverse of $organismNameMap */
    private $organismNumberMap;

    /** @var bool if this becomes true, the pattern is static and won't ever change - do not recompute, just keep bumping generations */
    private $stillLife = false;

    /**
     * @return bool
     */
    public function isStillLife(): bool
    {
        return $this->stillLife;
    }

    /**
     * @param bool $isStillLife
     */
    public function setStillLife(bool $isStillLife)
    {
        $this->stillLife = $isStillLife;
    }

    public function __construct($organismNameMap = null, $generation = null)
    {
        $generation = (int)$generation;
        if (is_array($organismNameMap)) {
            $this->organismNameMap = $organismNameMap;
        } else {
            $this->organismNameMap = array();
        }
        if ($generation !== null) {
            $this->generation = $generation;
        } else {
            $this->generation = 0;
        }
    }

    /**
     * Return the ID of an organism on the given coords (0 for none)
     * @param int $x
     * @param int $y
     * @return int
     */
    public function getOrganism($x, $y): int
    {
        if (!isset($this->organisms[$x]) || !isset($this->organisms[$x][$y])) {
            return 0;
        } else {
            return $this->organisms[$x][$y];
        }
    }

    /**
     * Get all the organisms in a flat array
     * @return array
     */
    public function getAllOrganisms(): array
    {
        $results = array();
        foreach ($this->organisms as $x => $cols) {
            foreach ($cols as $y => $species) {
                $results[] = array(
                    'x' => $x,
                    'y' => $y,
                    'species' => $this->getOrganismNameByNumber($species)
                );
            }
        }
        return $results;
    }

    /**
     * Sets a cell with a given type of organism. If conflict, choose one.
     * @param int $x
     * @param int $y
     * @param int $organism
     * @return int the organism that's set
     */
    public function setOrganism($x, $y, $organism): int
    {
        if (!isset($this->organisms[$x])) {
            $this->organisms[$x] = array();
        }
        if (isset($this->organisms[$x][$y])) {
            // already occupied - find who survives
            $organism = $this->findSurvivor($this->organisms[$x][$y], $organism);
        }
        $this->organisms[$x][$y] = $organism;
        return $organism;
    }

    /**
     * Clears the cell unconditionally
     * @param $x
     * @param $y
     */
    public function clearOrganism($x, $y)
    {
        if (isset($this->organisms[$x], $this->organisms[$x][$y])) {
            unset($this->organisms[$x][$y]);
        }
    }

    /**
     * Clears the cell if occupied by this type of organism
     * @param int $x
     * @param int $y
     * @param int $organism
     */
    public function clearOrganismType($x, $y, $organism)
    {
        if (isset($this->organisms[$x], $this->organisms[$x][$y]) && $this->organisms[$x][$y] == $organism) {
            unset($this->organisms[$x][$y]);
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
    public function setMaxIterations($maxIterations): LifeBoard
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
        $alreadyInitialized = $this->isInitialized();
        if (!$alreadyInitialized) {
            $this->initialized = ($this->getMaxIterations() > 0) && ($this->getEdgeSize() > 0) && ($this->getSpeciesCount() > 0);
            /*
             * @todo perhaps check if this would be faster than creating at access time?
             * @see $this->setOrganism()
            if ($this->initialized) {
                for ($x = $this->getEdgeSize() - 1; $x >= 0; $x--) {
                    $this->organisms[$x] = array();
                }
            }
            */
        }
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
    public function setEdgeSize($edgeSize): LifeBoard
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
    public function setSpeciesCount($speciesCount): LifeBoard
    {
        if ($speciesCount <= 0) {
            throw new \InvalidArgumentException('Species count must be >0');
        }

        $this->speciesCount = $speciesCount;
        $this->initializeBoard();
        return $this;
    }

    /**
     * @param int $oneOrganism
     * @param int $anotherOrganism
     * @return int the ID of the surviving organism
     */
    private function findSurvivor($oneOrganism, $anotherOrganism): int
    {
        // @todo perhaps mt_rand() is overkill, but rand() doesn't quite guarantee a fair coin toss
        return (mt_rand(0, 1) > 0) ? $oneOrganism : $anotherOrganism;
    }

    /**
     * @param int $x
     * @param int $y
     * @param int $organism
     * @return int
     * @throws BoardStateException
     */
    public function importOrganism($x, $y, $organism): int
    {
        if ($this->isInitialized()) {
            if ($x >= $this->getEdgeSize() || $y >= $this->getEdgeSize() || $x < 0 || $y < 0) {
                throw new \InvalidArgumentException('Cell outside board!');
            }
            $organismNumber = $this->getNumberByOrganismName($organism);
            return $this->setOrganism($x, $y, $organismNumber);
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
     * @param string $organism
     * @return int
     * @throws TooManySpeciesException
     */
    public function getNumberByOrganismName($organism): int
    {
        if (!isset($this->organismNameMap[$organism])) {
            $nextCount = count($this->organismNameMap) + 1;
            if ($nextCount < $this->speciesCount) {
                $this->organismNameMap[$organism] = $nextCount;
            } else {
                throw new TooManySpeciesException('Attempted to add too many species');
            }
        }
        return $this->organismNameMap[$organism];
    }

    /**
     * Map the internal representation into a string
     * @param int $organismNumber
     * @return string
     */
    public function getOrganismNameByNumber($organismNumber): string
    {
        if (is_null($this->organismNumberMap)) {
            $this->organismNumberMap = array_flip($this->organismNameMap);
        }
        if (!isset($this->organismNumberMap[$organismNumber])) {
            return '.';
        }
        return $this->organismNumberMap[$organismNumber];
    }

    /**
     * Debug function to return the current state as a string
     * @return string
     */
    public function getStringMap(): string
    {
        $string = '';
        for ($y = $this->edgeSize - 1; $y >= 0; $y--) {
            for ($x = $this->edgeSize - 1; $x >= 0; $x--) {
                $string .= $this->getOrganismNameByNumber($this->getOrganism($x, $y));
            }
            $string .= "\n";
        }
        return $string;
    }

    /**
     * @return LifeBoard|null
     */
    public function nextGeneration()
    {
        if ($this->getMaxIterations() <= $this->generation) {
            return null;
        }
        if ($this->isStillLife()) { // nothing more to do here
            $this->generation++;
            return $this;
        } else {
            $newBoard = new LifeBoard($this->organismNameMap, $this->generation + 1);
            $newBoard->setSpeciesCount($this->getSpeciesCount())
                ->setEdgeSize($this->getEdgeSize())
                ->setMaxIterations($this->getMaxIterations());
            $newBoard->calculateChildren($this);
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
    private function calculateChildren($oldBoard)
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
    public function countLiveNeighbors($x, $y, $species): int
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
        return $this->getStringMap();
    }

    /**
     * Inefficient, but workable.
     * @param LifeBoard $lb
     * @return bool
     */
    public function equals(LifeBoard $lb): bool
    {
        return (string)$this === (string)$lb;
    }

}