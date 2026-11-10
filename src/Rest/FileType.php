<?php

declare(strict_types=1);

namespace SolveBeam\Twinfield\Rest;

/**
 * File types of the imports and exports of the REST API (`fileType`).
 */
enum FileType: string
{
    case Csv = 'csv';
    case Xlsx = 'xlsx';

    public function contentType(): string
    {
        return match ($this) {
            self::Csv => 'text/csv',
            self::Xlsx => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        };
    }
}
