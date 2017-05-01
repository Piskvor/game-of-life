<?php
declare(strict_types=1);

namespace Piskvor\LivenessCalculator;

use Piskvor\LifeBoard;

class LifeBoardCalculator implements LivenessCalculatorInterface
{
    /** @var LifeBoard with the previous world state */
    private $oldBoard;

    /**
     * Returns a new LifeBoard with the state of the next generation.
     * NOTE that we are NOT changing state of $this, we are making a new instance for the new state.
     * (Except in the degenerate case of still lives, where we don't compute anything, just bump the generation counter up)
     * @param LifeBoard $oldBoard the previous world state
     * @return LifeBoard the new world state
     */
    public function getNextBoard(LifeBoard $oldBoard): LifeBoard
    {
        $this->oldBoard = $oldBoard;
        if ($this->oldBoard->getMaxGenerations() <= $this->oldBoard->getGeneration()) { // done
            $this->oldBoard->setFinished(true);
            return $this->oldBoard;
        }
        if ($this->oldBoard->isStillLife()) { // nothing more to do here
            $this->oldBoard->incrementGeneration();
            return $this->oldBoard;
        } else { // calculate state of next generation
            $newBoard = new LifeBoard($this->oldBoard->getSpeciesNameMap(), $this->oldBoard->getGeneration() + 1);
            $newBoard->setSpeciesCount($this->oldBoard->getSpeciesCount());
            $newBoard->setEdgeSize($this->oldBoard->getEdgeSize());
            $newBoard->setMaxGenerations($this->oldBoard->getMaxGenerations());

            $this->calculateNewGeneration($newBoard);

            if ($newBoard->equals($this->oldBoard)) {
                // no more changes
                $newBoard->setStillLife(true);
            }
            return $newBoard;
        }
    }

    /**
     * Actual calculation happens here. We're using the most naive and sloooooow O(c*c*s) method here.
     * Possible optimizations: check for still life, check for loops, ignore empty areas, ignore already processed cells and only check once per point. Or go for HashLife, currently most efficient.
     * @param LifeBoard $newBoard
     */
    private function calculateNewGeneration(LifeBoard $newBoard)
    {
        for ($species = $newBoard->getSpeciesCount(); $species > 0; $species--) {
            for ($xPos = $newBoard->getEdgeSize() - 1; $xPos >= 0; $xPos--) {
                for ($yPos = $newBoard->getEdgeSize() - 1; $yPos >= 0; $yPos--) {
                    $liveNeighbors = $this->countLiveNeighbors($xPos, $yPos, $species);
                    switch ($liveNeighbors) {
                        case 0:
                        case 1:
                            // too lonely, die of boredom
                            $newBoard->clearOrganismSpecies($xPos, $yPos, $species);
                            break;
                        // just right, survives: but check if there's a species conflict
                        case 2:
                            if ($this->oldBoard->getOrganism($xPos, $yPos) === $species) {
                                $newBoard->setOrganism($xPos, $yPos, $species);
                            } // else empty, no-op
                            break;
                        case 3:
                            // just right - if empty, reproduced here, if not, survive
                            // check for species conflict
                            $newBoard->setOrganism($xPos, $yPos, $species);
                            break;
                        default:
                            // too crowded, die of claustrophobia
                            $newBoard->clearOrganismSpecies($xPos, $yPos, $species);
                    }
                }
            }
        }
        // @fixme: delta pattern; why, of course this isn't efficient
    }

    /**
     * Calculates live neighbors for the given cell.
     * @TODO This could be optimized many ways: recalculate only a part of the grid etc.
     * @param int $xPos
     * @param int $yPos
     * @param int $species ID of the species - do not calculate others!
     * @return int count of live neighbors
     */
    public function countLiveNeighbors(int $xPos, int $yPos, int $species): int
    {
        $liveNeighborCount = 0;
        //check the top and bottom rows
        for ($xNeighborOffset = -1; $xNeighborOffset <= 1; $xNeighborOffset++) {
            for ($yNeighborOffset = -1; $yNeighborOffset <= 1; $yNeighborOffset += 2) {
                if ($this->oldBoard->getOrganism($xPos + $xNeighborOffset, $yPos + $yNeighborOffset) === $species) {
                    $liveNeighborCount++;
                }
            }
        }
        // check the middle
        for ($xNeighborOffset = -1; $xNeighborOffset <= 1; $xNeighborOffset += 2) {
            if ($this->oldBoard->getOrganism($xPos + $xNeighborOffset, $yPos) === $species) {
                $liveNeighborCount++;
            }
        }
        return $liveNeighborCount;
    }

}