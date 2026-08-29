<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\WithTitle;

/** Listado de revisiones de almacén con los mismos filtros de la pantalla. */
class AlmacenesExport extends ReporteExcel implements WithTitle
{
    public function __construct(
        private readonly Collection $almacenes,
        private readonly array $resumen,
        array $meta = [],
    ) {
        parent::__construct($meta);
    }

    public function title(): string
    {
        return 'Revisiones';
    }

    public function titulo(): string
    {
        return 'Reporte de revisiones de almacén';
    }

    public function subtitulo(): string
    {
        return 'Control del stock físico de la tienda';
    }

    public function indicadores(): array
    {
        return [
            'Revisiones' => $this->almacenes->count(),
            'En revisión' => (int) ($this->resumen['en_revision'] ?? 0),
            'Aplicadas' => (int) ($this->resumen['aplicados'] ?? 0),
            'Anuladas' => (int) ($this->resumen['anulados'] ?? 0),
            'Productos revisados' => (int) ($this->resumen['productos_revisados'] ?? 0),
            'Valor de las diferencias Bs' => round((float) ($this->resumen['diferencia_valor'] ?? 0), 2),
        ];
    }

    public function cabeceras(): array
    {
        return [
            'N°', 'Número', 'Fecha', 'Hora', 'Descripción', 'Creó', 'Estado', 'Productos',
            'Cantidad contada', 'Costo Bs', 'Aplicó', 'Fecha aplicado', 'Observación',
        ];
    }

    public function filas(): array
    {
        return $this->almacenes->values()->map(fn ($almacen, $indice) => [
            $indice + 1,
            $almacen->numero,
            $this->fechaExcel($almacen->fecha),
            $this->fechaExcel($almacen->fecha),
            $almacen->descripcion,
            $almacen->usuario_nombre,
            $almacen->estado === 'BORRADOR' ? 'EN REVISIÓN' : $almacen->estado,
            (int) ($almacen->detalles_count ?? $almacen->detalles()->count()),
            (float) $almacen->total_cantidad,
            (float) $almacen->total_costo,
            $almacen->aplicado_por_nombre,
            $this->fechaExcel($almacen->fecha_aplicado),
            $almacen->observacion,
        ])->all();
    }

    public function totales(): array
    {
        return [
            null, null, null, null, null, null, 'TOTALES',
            (int) $this->almacenes->sum(fn ($almacen) => (int) ($almacen->detalles_count ?? 0)),
            (float) $this->almacenes->sum(fn ($almacen) => (float) $almacen->total_cantidad),
            (float) $this->almacenes->sum(fn ($almacen) => (float) $almacen->total_costo),
            null, null, null,
        ];
    }

    public function columnasTexto(): array
    {
        return ['B'];
    }

    public function formatos(): array
    {
        return [
            'C' => 'dd/mm/yyyy',
            'D' => 'hh:mm',
            'H' => '#,##0',
            'I' => '#,##0.000',
            'J' => '#,##0.00',
            'L' => 'dd/mm/yyyy hh:mm',
        ];
    }

    public function anchos(): array
    {
        return [
            'A' => 5, 'B' => 13, 'C' => 11, 'D' => 8, 'E' => 34, 'F' => 20, 'G' => 14,
            'H' => 10, 'I' => 15, 'J' => 13, 'K' => 20, 'L' => 17, 'M' => 34,
        ];
    }
}
