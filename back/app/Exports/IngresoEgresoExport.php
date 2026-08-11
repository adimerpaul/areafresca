<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Reporte "ingreso y egreso": una fila por movimiento de stock, con el mismo
 * juego de columnas, anchos y formatos numéricos que el reporte del sistema
 * anterior, para que se pueda comparar hoja contra hoja.
 */
class IngresoEgresoExport extends DefaultValueBinder implements FromCollection, WithColumnFormatting, WithColumnWidths, WithCustomValueBinder, WithHeadings, WithStrictNullComparison, WithStyles
{
    public function __construct(private readonly Collection $movements) {}

    public function collection(): Collection
    {
        return $this->movements->map(fn (array $movement) => [
            $movement['codigo'],
            $movement['nombre'],
            Date::dateTimeToExcel($movement['fecha']),
            (float) $movement['entrada'],
            (float) $movement['salida'],
            $movement['motivo'],
            (float) $movement['existencia'],
            $movement['comanda'],
            (int) $movement['factura'],
            '',
        ]);
    }

    public function headings(): array
    {
        return [
            'CODIGO', 'PRODUCTO', 'FECHA REGISTRO', 'ENTRADA', 'SALIDA',
            'MOTIVO INGRE/EGRE', 'EXISTENCIA', 'NRO COMANDA', 'NRO FACTURA', 'MOTIVO INGR. STOCK',
        ];
    }

    /**
     * Códigos y números de comanda son etiquetas, no cantidades: si Excel los
     * toma como número pierde los ceros a la izquierda y el prefijo.
     */
    public function bindValue(Cell $cell, $value): bool
    {
        if (in_array($cell->getColumn(), ['A', 'H'], true) && $cell->getRow() > 1) {
            $cell->setValueExplicit((string) $value, DataType::TYPE_STRING);

            return true;
        }

        return parent::bindValue($cell, $value);
    }

    public function columnFormats(): array
    {
        return [
            'A' => '@',
            'C' => 'm/d/yyyy h:mm',
            'D' => '######0.00',
            'E' => '######0.00',
            'H' => '@',
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 16.71, 'B' => 36.71, 'C' => 16.71, 'D' => 8.71, 'E' => 7,
            'F' => 26.71, 'G' => 10.57, 'H' => 13.42, 'I' => 12.57, 'J' => 26.71,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $sheet->getStyle('A1:J1')->applyFromArray([
            'font' => ['bold' => true, 'name' => 'Arial', 'size' => 9],
        ]);
        $sheet->freezePane('A2');

        return [];
    }
}
