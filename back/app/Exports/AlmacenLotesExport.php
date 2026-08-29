<?php

namespace App\Exports;

use App\Models\Almacen;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\WithTitle;

/** Hoja "Lotes": cada lote contado, para revisar vencimientos uno por uno. */
class AlmacenLotesExport extends ReporteExcel implements WithTitle
{
    public function __construct(
        private readonly Almacen $almacen,
        private readonly Collection $detalles,
        array $meta = [],
    ) {
        parent::__construct($meta);
    }

    public function title(): string
    {
        return 'Lotes';
    }

    public function titulo(): string
    {
        return "Lotes y vencimientos contados en la revisión {$this->almacen->numero}";
    }

    public function subtitulo(): string
    {
        return 'Sólo los productos que se contaron lote por lote';
    }

    public function cabeceras(): array
    {
        return ['N°', 'Código', 'Producto', 'Unidad', 'Lote', 'Vencimiento', 'Días para vencer', 'Cantidad', 'Contó', 'Fecha registro', 'Hora registro'];
    }

    public function filas(): array
    {
        $filas = [];
        foreach ($this->detalles as $detalle) {
            foreach ($detalle->conteos as $conteo) {
                $filas[] = [
                    count($filas) + 1,
                    $detalle->codigo,
                    $detalle->nombre,
                    $detalle->unidad,
                    $conteo->lote ?: 'SIN LOTE',
                    $this->fechaExcel($conteo->fecha_vencimiento),
                    $conteo->fecha_vencimiento ? (int) now()->startOfDay()->diffInDays($conteo->fecha_vencimiento->startOfDay(), false) : null,
                    (float) $conteo->cantidad,
                    $detalle->usuario_nombre,
                    $this->fechaExcel($detalle->created_at),
                    $this->fechaExcel($detalle->created_at),
                ];
            }
        }

        return $filas;
    }

    public function totales(): array
    {
        $total = $this->detalles->sum(fn ($detalle) => $detalle->conteos->sum(fn ($conteo) => (float) $conteo->cantidad));

        return [null, null, null, null, null, null, 'TOTAL', round($total, 3), null, null, null];
    }

    public function columnasTexto(): array
    {
        return ['B', 'E'];
    }

    public function formatos(): array
    {
        return ['F' => 'dd/mm/yyyy', 'G' => '#,##0', 'H' => '#,##0.000', 'J' => 'dd/mm/yyyy', 'K' => 'hh:mm'];
    }

    public function anchos(): array
    {
        return ['A' => 5, 'B' => 13, 'C' => 36, 'D' => 9, 'E' => 18, 'F' => 13, 'G' => 15, 'H' => 12, 'I' => 20, 'J' => 13, 'K' => 9];
    }
}
