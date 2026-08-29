<?php

namespace App\Exports;

use App\Models\Almacen;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\WithTitle;

/** Hoja "Por usuario": cuánto contó cada persona y en qué horario lo hizo. */
class AlmacenUsuariosExport extends ReporteExcel implements WithTitle
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
        return 'Por usuario';
    }

    public function titulo(): string
    {
        return "Avance por usuario de la revisión {$this->almacen->numero}";
    }

    public function subtitulo(): string
    {
        return 'Productos cargados por cada persona y horario en el que trabajó';
    }

    public function cabeceras(): array
    {
        return ['N°', 'Usuario', 'Productos contados', 'Cantidad contada', 'Con diferencia', 'Valor de la diferencia Bs', 'Primer registro', 'Último registro'];
    }

    public function filas(): array
    {
        return $this->detalles->groupBy(fn ($detalle) => $detalle->usuario_nombre ?: '—')
            ->map(fn (Collection $grupo, $usuario) => [
                'usuario' => $usuario,
                'productos' => $grupo->count(),
                'cantidad' => round($grupo->sum(fn ($detalle) => (float) $detalle->cantidad), 3),
                'diferencias' => $grupo->filter(fn ($detalle) => abs((float) $detalle->diferencia_actual) > 0.0001)->count(),
                'valor' => round($grupo->sum(fn ($detalle) => (float) $detalle->diferencia_actual * (float) $detalle->precio_compra), 2),
                'desde' => $grupo->min(fn ($detalle) => $detalle->created_at),
                'hasta' => $grupo->max(fn ($detalle) => $detalle->created_at),
            ])
            ->sortByDesc('productos')
            ->values()
            ->map(fn (array $fila, $indice) => [
                $indice + 1, $fila['usuario'], $fila['productos'], $fila['cantidad'], $fila['diferencias'], $fila['valor'],
                $this->fechaExcel($fila['desde']), $this->fechaExcel($fila['hasta']),
            ])->all();
    }

    public function totales(): array
    {
        return [
            null, 'TOTALES',
            $this->detalles->count(),
            round($this->detalles->sum(fn ($detalle) => (float) $detalle->cantidad), 3),
            $this->detalles->filter(fn ($detalle) => abs((float) $detalle->diferencia_actual) > 0.0001)->count(),
            round($this->detalles->sum(fn ($detalle) => (float) $detalle->diferencia_actual * (float) $detalle->precio_compra), 2),
            null, null,
        ];
    }

    public function formatos(): array
    {
        return ['C' => '#,##0', 'D' => '#,##0.000', 'E' => '#,##0', 'F' => '#,##0.00', 'G' => 'dd/mm/yyyy hh:mm', 'H' => 'dd/mm/yyyy hh:mm'];
    }

    public function anchos(): array
    {
        return ['A' => 5, 'B' => 28, 'C' => 18, 'D' => 17, 'E' => 15, 'F' => 22, 'G' => 18, 'H' => 18];
    }
}
