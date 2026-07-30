<?php

namespace App\Support;

use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;

class MaeChunkReadFilter implements IReadFilter
{
    private int $startRow;
    private int $endRow;

    public function __construct(int $startRow = 1, int $chunkSize = 1)
    {
        $this->setRows($startRow, $chunkSize);
    }

    public function setRows(int $startRow, int $chunkSize): void
    {
        $this->startRow = max(1, $startRow);
        $this->endRow = max($this->startRow, $this->startRow + max(1, $chunkSize) - 1);
    }

    public function readCell($columnAddress, $row, $worksheetName = ''): bool
    {
        return $row >= $this->startRow && $row <= $this->endRow;
    }
}
