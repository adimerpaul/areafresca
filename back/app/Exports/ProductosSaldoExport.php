<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ProductosSaldoExport extends DefaultValueBinder implements FromCollection, ShouldAutoSize, WithColumnFormatting, WithCustomValueBinder, WithHeadings, WithStyles
{
    public function __construct(private readonly Collection $products) {}

    public function collection(): Collection
    {
        $rows = $this->products->values()->map(function ($product, int $index) {
            $row = $index + 2;

            return [
                $product->codigo_barras ?: $product->codigo,
                $product->nombre,
                $product->fecha_registro ? Date::dateTimeToExcel($product->fecha_registro) : null,
                (float) $product->stock_inicial,
                (float) $product->precio_compra,
                (float) $product->precio_venta,
                "=D{$row}*E{$row}",
                "=F{$row}-E{$row}",
            ];
        });

        $lastDataRow = $this->products->count() + 1;
        $totalFormula = $this->products->isEmpty() ? 0 : "=SUM(G2:G{$lastDataRow})";
        $rows->push([null, null, null, null, null, 'TOTAL GENERAL', $totalFormula, null]);

        return $rows;
    }

    public function bindValue(Cell $cell, $value): bool
    {
        if ($cell->getColumn() === 'A' && $cell->getRow() > 1) {
            $cell->setValueExplicit((string) $value, DataType::TYPE_STRING);

            return true;
        }

        return parent::bindValue($cell, $value);
    }

    public function headings(): array
    {
        return ['Cod. Producto', 'Producto', 'Fecha Reg.', 'Saldo', 'Pre.Compra', 'Pre.Venta', 'Total Bs.', 'Ganancia'];
    }

    public function columnFormats(): array
    {
        return [
            'A' => '@',
            'C' => 'dd/mm/yyyy',
            'D' => '#,##0.000',
            'E' => '#,##0.00',
            'F' => '#,##0.00',
            'G' => '#,##0.00',
            'H' => '#,##0.00',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $lastDataRow = $this->products->count() + 1;
        $totalRow = $lastDataRow + 1;
        $sheet->getStyle('A1:H1')->applyFromArray([
            'font' => ['bold' => true],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'FFFF00'],
            ],
        ]);
        $sheet->getStyle("F{$totalRow}:G{$totalRow}")->applyFromArray([
            'font' => ['bold' => true],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'FFFF00'],
            ],
        ]);
        $sheet->setAutoFilter("A1:H{$lastDataRow}");
        $sheet->freezePane('A2');

        return [];
    }
}
