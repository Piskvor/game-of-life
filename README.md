# game-of-life
Conway's Game Of Life with multiple species

## usage

`php gol.php inputfile.xml`

## explicit rules

- square world `n`&times;`n`
- `m` species
- 8 surrounding cells (straight and diagonal)
- organism surrounded by <2 same-species organisms: dies
- organism surrounded by 2-3 same-species organisms: survives
- organism surrounded by >3 same-species organisms: dies
- empty cell surrounded by 3 same-species organisms: new organism
- two organisms at one cell: random one survives, other dies

## implicit rules

- species do not influence each other (except when conflicted)
- coordinates are 0-based, increasing (e.g. `n=10` gives a 100-cell grid from [0,0] to [9,9])
- invalid data (e.g. outside grid) are rejected with the whole import (dropping them is another option, I don't think either of these is universally applicable)