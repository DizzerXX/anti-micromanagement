<?php

declare(strict_types=1);

namespace App\Domain\Reports\Export;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * One worksheet. Date columns hold Excel serials formatted as dates.
 */
class ReportSheet implements FromArray, ShouldAutoSize, WithEvents, WithHeadings, WithStyles, WithTitle
{
    private const DATE_FORMAT = 'ddd d mmm yyyy';

    private const DATE_TIME_FORMAT = 'ddd d mmm yyyy, hh:mm';

    /**
     * @param  list<string>  $headings
     * @param  list<array<int, mixed>>  $rows
     * @param  list<string>  $dateColumns
     * @param  list<int>|null  $dateRows  restrict date formatting to these rows (1-based)
     */
    public function __construct(
        private readonly string $title,
        private readonly array $headings,
        private readonly array $rows,
        private readonly array $dateColumns = [],
        private readonly ?array $dateRows = null,
        private readonly ?string $dateTimeCell = null,
        private readonly bool $boldLastRow = false,
    ) {}

    public function title(): string
    {
        return $this->title;
    }

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return $this->headings;
    }

    /**
     * @return list<array<int, mixed>>
     */
    public function array(): array
    {
        return $this->rows;
    }

    /**
     * @return array<int|string, array<string, mixed>>
     */
    public function styles(Worksheet $sheet): array
    {
        $styles = $this->headings === [] ? ['A' => ['font' => ['bold' => true]]] : [1 => ['font' => ['bold' => true]]];

        if ($this->boldLastRow) {
            $styles[count($this->rows) + 1] = ['font' => ['bold' => true]];
        }

        return $styles;
    }

    /**
     * @return array<class-string, callable>
     */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();
                $offset = $this->headings === [] ? 0 : 1;
                $last = count($this->rows) + $offset;

                foreach ($this->dateColumns as $column) {
                    $rows = $this->dateRows ?? range($offset + 1, max($offset + 1, $last));
                    foreach ($rows as $row) {
                        $sheet->getStyle("{$column}{$row}")->getNumberFormat()->setFormatCode(self::DATE_FORMAT);
                    }
                }

                if ($this->dateTimeCell !== null) {
                    $sheet->getStyle($this->dateTimeCell)->getNumberFormat()->setFormatCode(self::DATE_TIME_FORMAT);
                }

                if ($this->headings !== []) {
                    $sheet->freezePane('A2');
                }

                $sheet->getStyle('A1:Z'.max(1, $last))->getAlignment()->setVertical('top');
                unset($sheet, $last);
            },
        ];
    }
}
