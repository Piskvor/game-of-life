<?php


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

    public function __construct($organismNameMap = null, $generation = null)
    {
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
    public function getOrganism($x, $y)
    {
        if (!isset($this->organisms[$x]) || !isset($this->organisms[$x][$y])) {
            return 0;
        } else {
            return $this->organisms[$x][$y];
        }
    }

    /**
     * Sets a cell with a given type of organism. If conflict, choose one.
     * @param int $x
     * @param int $y
     * @param int $organism
     */
    public function setOrganism($x, $y, $organism)
    {
        if (!isset($this->organisms[$x])) {
            $this->organisms[$x] = array();
        }
        if (isset($this->organisms[$x][$y])) {
            // already occupied - find who survives
            $organism = $this->findSurvivor($this->organisms[$x][$y], $organism);
        }
        $this->organisms[$x][$y] = $organism;
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
    public function getMaxIterations()
    {
        return $this->maxIterations;
    }

    /**
     * @param int $maxIterations
     */
    public function setMaxIterations($maxIterations)
    {
        if ($maxIterations <= 0) {
            throw new \InvalidArgumentException('Iterations must be >0');
        }

        $this->maxIterations = $maxIterations;
        $this->initializeBoard();
    }

    /**
     * @return bool true if all necessary data has been set
     */
    public function isInitialized()
    {
        return $this->initialized;
    }

    /**
     * @return LifeBoard
     */
    private function initializeBoard()
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
    public function getGeneration()
    {
        return $this->generation;
    }

    /**
     * @return int
     */
    public function getEdgeSize()
    {
        return $this->edgeSize;
    }

    /**
     * @param int $edgeSize
     * @return LifeBoard
     */
    public function setEdgeSize($edgeSize)
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
    public function getSpeciesCount()
    {
        return $this->speciesCount;
    }

    /**
     * @param int $speciesCount
     * @return LifeBoard
     */
    public function setSpeciesCount($speciesCount)
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
    private function findSurvivor($oneOrganism, $anotherOrganism)
    {
        // @todo perhaps mt_rand() is overkill, but rand() doesn't quite guarantee a fair coin toss
        return (mt_rand(0, 1) > 0) ? $oneOrganism : $anotherOrganism;
    }

    public function importOrganism($x, $y, $organism)
    {
        if ($this->isInitialized()) {
            $organismNumber = $this->getNumberByOrganismName($organism);
            $this->setOrganism($x, $y, $organismNumber);
        } else {
            throw new BoardStateException('Board not configured yet!');
        }
    }

    /**
     * Map the string name of an organism into an internal representation
     * @param string $organism
     * @return int
     * @throws TooManySpeciesException
     */
    private function getNumberByOrganismName($organism)
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
    private function getOrganismNameByNumber($organismNumber)
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
    public function getStringMap()
    {
        $string = '';
        for ($x = 0; $x < $this->edgeSize; $x++) {
            for ($y = 0; $y < $this->edgeSize; $y++) {
                $string .= $this->getOrganismNameByNumber($this->getOrganism($x, $y));
            }
            $string .= "\n ";
        }
        return trim($string);
    }

    /**
     * @return LifeBoard|null
     */
    public function nextGeneration()
    {
        if ($this->getMaxIterations() <= $this->generation) {
            return null;
        }
        $newBoard = new LifeBoard($this->organismNameMap, $this->generation + 1);
        $newBoard->setSpeciesCount($this->getSpeciesCount())
            ->setEdgeSize($this->getEdgeSize())
            ->setMaxIterations($this->getMaxIterations());
        $newBoard->calculateChildren($this);
        return $newBoard;
    }

    /**
     * Actual calculation happens here. We're using the most naive and sloooooow O(c*c*s) method here.
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
                            } // else noop
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

    public function countLiveNeighbors($x, $y, $species)
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
}