<?php
declare(strict_types=1);


namespace Piskvor;

/**
 * Trait LifeBoardCalculator contains the logic that computes new board state given the old one.
 * @package Piskvor
 */
trait LifeBoardCalculator
{
    /**
     * Returns a new LifeBoard with the state of the next generation.
     * NOTE that we are NOT changing state of $this, we are making a new instance for the new state.
     * (Except in the degenerate case of still lives, where we don't compute anything, just bump the generation counter up)
     * @return LifeBoard
     */
    public function getNextBoard(): LifeBoard
    {
        if ($this->maxGenerations <= $this->generation) { // done
            $this->finished = true;
            return $this;
        }
        if ($this->isStillLife()) { // nothing more to do here
            $this->generation++;
            return $this;
        } else { // calculate state of next generation
            $newBoard = new LifeBoard($this->speciesNameMap, $this->generation + 1);
            $newBoard->setSpeciesCount($this->getSpeciesCount());
            $newBoard->setEdgeSize($this->getEdgeSize());
            $newBoard->setMaxGenerations($this->maxGenerations);

            $newBoard->calculateNewGeneration($this);

            if ($newBoard->equals($this)) {
                // no more changes
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
        for ($species = $this->speciesCount; $species > 0; $species--) {
            for ($xPos = $this->edgeSize - 1; $xPos >= 0; $xPos--) {
                for ($yPos = $this->edgeSize - 1; $yPos >= 0; $yPos--) {
                    $liveNeighbors = $oldBoard->countLiveNeighbors($xPos, $yPos, $species);
                    switch ($liveNeighbors) {
                        case 0:
                        case 1:
                            // too lonely, die of boredom
                            $this->clearOrganismSpecies($xPos, $yPos, $species);
                            break;
                        case 2:
                            // just right, survives: but check if there's a species conflict
                            if ($oldBoard->getOrganism($xPos, $yPos) === $species) {
                                $this->setOrganism($xPos, $yPos, $species);
                            } // else empty, no-op
                            break;
                        case 3:
                            // just right - if empty, reproduced here, if not, survive
                            // check for species conflict
                            $this->setOrganism($xPos, $yPos, $species);
                            break;
                        default:
                            // too crowded, die of claustrophobia
                            $this->clearOrganismSpecies($xPos, $yPos, $species);
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
                if ($this->getOrganism($xPos + $xNeighborOffset, $yPos + $yNeighborOffset) === $species) {
                    $liveNeighborCount++;
                }
            }
        }
        // check the middle
        for ($xNeighborOffset = -1; $xNeighborOffset <= 1; $xNeighborOffset += 2) {
            if ($this->getOrganism($xPos + $xNeighborOffset, $yPos) === $species) {
                $liveNeighborCount++;
            }
        }
        return $liveNeighborCount;
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

}