<?php

namespace App\Exports;

use App\Models\Almacen;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\WithTitle;

/**
 * Hoja "Detalle": una fila por producto contado, con lo que decía el sistema,
 * lo que se contó, la diferencia y a qué hora lo cargó cada persona.
 */
class AlmacenDetalleExport extends ReporteExcel implements WithTitle
{
    public function __construct(
        private readonly Almacen $almacen,
        private readonly Collection $detalles,
        private readonly array $resumen,
        array $meta = [],
    ) {
        parent::__construct($meta);
    }

    public function title(): string
    {
        return 'Detalle';
    }

    public function titulo(): string
    {
        return "Revisión de almacén {$this->almacen->numero}";
    }

    public function subtitulo(): string
    {
        $estado = $this->almacen->estado === 'BORRADOR' ? 'EN REVISIÓN' : $this->almacen->estado;
        $aplicado = $this->almacen->fecha_aplicado
            ? '  ·  Aplicada por '.($this->almacen->aplicado_por_nombre ?: '—').' el '.$this->almacen->fecha_aplicado->format('d/m/Y H:i')
            : '';

        return ($this->almacen->descripcion ?: 'Conteo del stock físico')
            ."  ·  Estado: {$estado}  ·  Creada por ".($this->almacen->usuario_nombre ?: '—')
            .' el '.optional($this->almacen->fecha)->format('d/m/Y H:i').$aplicado;
    }

    public function indicadores(): array
    {
        return [
            'Productos revisados' => (int) ($this->resumen['revisados'] ?? $this->detalles->count()),
            'Del catálogo' => (int) ($this->resumen['total_productos'] ?? 0),
            'Cuadran' => (int) ($this->resumen['sin_diferencia'] ?? 0),
            'Con diferencia' => (int) ($this->resumen['con_diferencia'] ?? 0),
            'Sobrantes' => round((float) ($this->resumen['sobrante'] ?? 0), 3),
            'Faltantes' => round((float) ($this->resumen['faltante'] ?? 0), 3),
            'Valor de la diferencia Bs' => round((float) ($this->resumen['diferencia_valor'] ?? 0), 2),
        ];
    }

    public function cabeceras(): array
    {
        return [
            'N°', 'Código', 'Producto', 'Unidad', 'Stock sistema', 'Contado', 'Diferencia',
            'Precio compra Bs', 'Valor diferencia Bs', 'Total contado Bs',
            'Lotes y vencimientos', 'Contó', 'Fecha registro', 'Hora registro', 'Observación',
        ];
    }

    public function filas(): array
    {
        return $this->detalles->values()->map(function ($detalle, $indice) {
            $diferencia = (float) $detalle->diferencia_actual;

            return [
                $indice + 1,
                $detalle->codigo,
                $detalle->nombre,
                $detalle->unidad,
                (float) $detalle->stock_actual,
                (float) $detalle->cantidad,
                $diferencia,
                (float) $detalle->precio_compra,
                round($diferencia * (float) $detalle->precio_compra, 2),
                (float) $detalle->total,
                $this->lotes($detalle),
                $detalle->usuario_nombre,
                $this->fechaExcel($detalle->created_at),
                $this->fechaExcel($detalle->created_at),
                $detalle->observacion,
            ];
        })->all();
    }

    public function totales(): array
    {
        $diferencia = $this->detalles->sum(fn ($detalle) => (float) $detalle->diferencia_actual);
        $valor = $this->detalles->sum(fn ($detalle) => (float) $detalle->diferencia_actual * (float) $detalle->precio_compra);

        return [
            null, null, null, 'TOTALES',
            (float) $this->detalles->sum(fn ($detalle) => (float) $detalle->stock_actual),
            (float) $this->detalles->sum(fn ($detalle) => (float) $detalle->cantidad),
            round($diferencia, 3),
            null,
            round($valor, 2),
            (float) $this->detalles->sum(fn ($detalle) => (float) $detalle->total),
            null, null, null, null, null,
        ];
    }

    public function columnasTexto(): array
    {
        return ['B'];
    }

    public function formatos(): array
    {
        return [
            'E' => '#,##0.000', 'F' => '#,##0.000', 'G' => '+#,##0.000;-#,##0.000;0.000',
            'H' => '#,##0.0000', 'I' => '#,##0.00', 'J' => '#,##0.00',
            'M' => 'dd/mm/yyyy', 'N' => 'hh:mm',
        ];
    }

    public function anchos(): array
    {
        return [
            'A' => 5, 'B' => 13, 'C' => 36, 'D' => 9, 'E' => 13, 'F' => 12, 'G' => 12,
            'H' => 14, 'I' => 16, 'J' => 15, 'K' => 40, 'L' => 20, 'M' => 12, 'N' => 9, 'O' => 28,
        ];
    }

    private function lotes($detalle): string
    {
        return $detalle->conteos->map(fn ($conteo) => ($conteo->lote ?: 'sin lote')
            .': '.rtrim(rtrim(number_format((float) $conteo->cantidad, 3, '.', ''), '0'), '.')
            .($conteo->fecha_vencimiento ? ' (vence '.$conteo->fecha_vencimiento->format('d/m/Y').')' : ''))
            ->implode('  ·  ');
    }
}
