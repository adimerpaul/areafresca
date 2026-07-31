<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

class VentasExport implements FromCollection, ShouldAutoSize, WithHeadings
{
    public function __construct(private readonly Collection $ventas) {}

    public function collection(): Collection
    {
        return $this->ventas->map(fn ($v) => [
            $v->numero, $v->fecha->format('d/m/Y H:i'), $v->usuario_nombre,
            $v->tipo_pago, $v->monto_efectivo, $v->monto_qr,
            $v->subtotal, $v->descuento, $v->total, $v->estado,
        ]);
    }

    public function headings(): array
    {
        return ['Nº Venta', 'Fecha', 'Usuario', 'Tipo pago', 'Efectivo', 'QR', 'Subtotal', 'Descuento', 'Total', 'Estado'];
    }
}
