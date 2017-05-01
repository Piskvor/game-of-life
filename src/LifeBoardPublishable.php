<?php
declare(strict_types=1);


namespace Piskvor;

/**
 * Interface LifeBoardPublishable is the minimum amount of data that a GoL board needs to publish about itself.
 * @package Piskvor
 */
interface LifeBoardPublishable
{
    /**
     * @return int number of cells per side (the board is square: m x m cells). MUST be a positive integer.
     */
    public function getEdgeSize(): int;

    /**
     * @return int maximum number of species - MAY be reached, MUST NOT be exceeded
     */
    public function getSpeciesCount(): int;

    /**
     * @return int maximum number of generations to process. MUST be a positive integer
     */
    public function getMaxGenerations(): int;

    /**
     * @return string[][] array of cells with live organisms:
     * [{
     *  "x_pos": "1",
     *  "y_pos": "0",
     *  "species": "x"
     * }]
     */
    public function getOrganismList(): array ;

}