<?php

declare(strict_types=1);

namespace App\Enums;

enum ReportStatus: string
{
    case Ready = 'ready';
    case Partial = 'partial';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Ready => 'Ready',
            self::Partial => 'Partial',
            self::Failed => 'Failed',
        };
    }
}
