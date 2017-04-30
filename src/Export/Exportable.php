<?php
declare(strict_types=1);

namespace Piskvor\Export;


interface Exportable
{
    public function export(string $filename): bool;
}