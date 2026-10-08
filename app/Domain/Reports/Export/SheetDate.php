<?php

declare(strict_types=1);

namespace App\Domain\Reports\Export;

use Carbon\CarbonInterface;
use PhpOffice\PhpSpreadsheet\Shared\Date;

/**
 * Excel serial numbers, so dates are real date cells, not strings.
 */
final class SheetDate
{
    public static function cell(CarbonInterface $date, bool $withTime = false): float
    {
        $value = Date::dateTimeToExcel($withTime ? $date->toDateTime() : $date->copy()->startOfDay()->toDateTime());

        return is_float($value) ? $value : (float) $value;
    }
}
