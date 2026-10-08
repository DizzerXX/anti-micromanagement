<?php

declare(strict_types=1);

namespace App\Enums;

enum ReportFile: string
{
    case Pdf = 'pdf';
    case Xlsx = 'xlsx';

    public function label(): string
    {
        return match ($this) {
            self::Pdf => 'PDF',
            self::Xlsx => 'Spreadsheet',
        };
    }

    public function shortLabel(): string
    {
        return match ($this) {
            self::Pdf => 'PDF',
            self::Xlsx => 'XLSX',
        };
    }
}
