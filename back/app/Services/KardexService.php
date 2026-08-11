<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Libro de ingresos y egresos (kardex) por producto.
 *
 * Reúne en una sola línea de tiempo los tres orígenes que mueven
 * `productos.stock_inicial`: compras, ventas y bajas. Cada anulación genera un
 * segundo movimiento en sentido contrario, fechado cuando se anuló, porque así
 * fue como realmente se movió el stock.
 */
class KardexService
{
    /**
     * @param  Collection<int>  $productIds  productos ya filtrados por la pantalla
     * @return Collection<array<string,mixed>>
     */
    public function movements(Collection $productIds, ?Carbon $from = null, ?Carbon $to = null): Collection
    {
        if ($productIds->isEmpty()) {
            return collect();
        }

        $movements = collect()
            ->merge($this->purchases($productIds))
            ->merge($this->sales($productIds))
            ->merge($this->writeOffs($productIds));

        // El saldo se recorre sobre TODA la historia y recién después se recorta al
        // rango pedido: la existencia del primer día depende de lo que pasó antes.
        $balances = $this->openingBalances($productIds, $movements);

        return $movements
            ->sortBy(fn (array $movement) => sprintf(
                '%010d|%s|%d',
                $movement['producto_id'],
                $movement['fecha']->format('YmdHis'),
                $movement['orden'],
            ))
            ->map(function (array $movement) use (&$balances) {
                $balance = ($balances[$movement['producto_id']] ?? 0.0) + $movement['entrada'] - $movement['salida'];
                $balances[$movement['producto_id']] = $balance;
                $movement['existencia'] = round($balance, 3);

                return $movement;
            })
            ->filter(fn (array $movement) => (! $from || $movement['fecha']->gte($from))
                && (! $to || $movement['fecha']->lte($to)))
            ->sortBy(fn (array $movement) => $movement['fecha']->format('YmdHis'))
            ->values();
    }

    /**
     * Saldo con el que arranca cada producto antes del primer movimiento
     * conocido: se despeja del stock actual restándole todo lo que se movió.
     */
    private function openingBalances(Collection $productIds, Collection $movements): array
    {
        $current = DB::table('productos')->whereIn('id', $productIds)->whereNull('deleted_at')
            ->pluck('stock_inicial', 'id');

        $balances = [];
        foreach ($productIds as $id) {
            $balances[$id] = (float) ($current[$id] ?? 0);
        }
        foreach ($movements as $movement) {
            $balances[$movement['producto_id']] = ($balances[$movement['producto_id']] ?? 0.0)
                - $movement['entrada'] + $movement['salida'];
        }

        return $balances;
    }

    private function purchases(Collection $productIds): Collection
    {
        $rows = DB::table('compra_detalles')
            ->join('compras', 'compras.id', '=', 'compra_detalles.compra_id')
            ->whereNull('compras.deleted_at')
            ->whereIn('compra_detalles.producto_id', $productIds)
            ->select([
                'compra_detalles.producto_id', 'compra_detalles.codigo', 'compra_detalles.nombre',
                'compra_detalles.cantidad', 'compras.numero', 'compras.fecha', 'compras.estado',
                'compras.updated_at',
            ])
            ->get();

        return $rows->flatMap(function ($row) {
            $movements = [$this->movement($row, entrada: (float) $row->cantidad, fecha: $row->fecha)];
            if ($row->estado === 'ANULADA') {
                $movements[] = $this->movement($row, salida: (float) $row->cantidad,
                    fecha: $row->updated_at, motivo: 'ANULACIÓN DE COMPRA', orden: 1);
            }

            return $movements;
        });
    }

    private function sales(Collection $productIds): Collection
    {
        $rows = DB::table('venta_detalles')
            ->join('ventas', 'ventas.id', '=', 'venta_detalles.venta_id')
            ->whereNull('ventas.deleted_at')
            ->whereNull('venta_detalles.deleted_at')
            ->whereIn('venta_detalles.producto_id', $productIds)
            ->select([
                'venta_detalles.producto_id', 'venta_detalles.codigo', 'venta_detalles.nombre',
                'venta_detalles.cantidad', 'ventas.numero', 'ventas.fecha', 'ventas.estado',
                'ventas.updated_at', 'ventas.id as venta_id', 'ventas.tipo_comprobante',
            ])
            ->get();

        return $rows->flatMap(function ($row) {
            // El número de factura del SIAT es el id de la venta; los recibos van en cero.
            $invoice = $row->tipo_comprobante === 'FACTURA' ? (int) $row->venta_id : 0;
            $movements = [$this->movement($row, salida: (float) $row->cantidad, fecha: $row->fecha, factura: $invoice)];
            if ($row->estado === 'ANULADA') {
                $movements[] = $this->movement($row, entrada: (float) $row->cantidad,
                    fecha: $row->updated_at, motivo: 'ANULACIÓN DE VENTA', factura: $invoice, orden: 1);
            }

            return $movements;
        });
    }

    private function writeOffs(Collection $productIds): Collection
    {
        $rows = DB::table('baja_detalles')
            ->join('bajas', 'bajas.id', '=', 'baja_detalles.baja_id')
            ->whereNull('bajas.deleted_at')
            ->whereIn('baja_detalles.producto_id', $productIds)
            ->select([
                'baja_detalles.producto_id', 'baja_detalles.codigo', 'baja_detalles.nombre',
                'baja_detalles.cantidad', 'bajas.numero', 'bajas.fecha', 'bajas.estado',
                'bajas.updated_at', 'bajas.motivo',
            ])
            ->get();

        return $rows->flatMap(function ($row) {
            $motive = (string) ($row->motivo ?: 'BAJA');
            $movements = [$this->movement($row, salida: (float) $row->cantidad, fecha: $row->fecha, motivo: $motive)];
            if ($row->estado === 'ANULADA') {
                $movements[] = $this->movement($row, entrada: (float) $row->cantidad,
                    fecha: $row->updated_at, motivo: 'ANULACIÓN DE '.$motive, orden: 1);
            }

            return $movements;
        });
    }

    /**
     * `orden` desempata los movimientos que caen en el mismo segundo: la
     * anulación siempre va después del movimiento que revierte.
     */
    private function movement(
        object $row,
        float $entrada = 0.0,
        float $salida = 0.0,
        ?string $fecha = null,
        string $motivo = '',
        int $factura = 0,
        int $orden = 0,
    ): array {
        return [
            'producto_id' => (int) $row->producto_id,
            'codigo' => (string) $row->codigo,
            'nombre' => (string) $row->nombre,
            'fecha' => Carbon::parse($fecha),
            'entrada' => round($entrada, 3),
            'salida' => round($salida, 3),
            'motivo' => $motivo,
            'existencia' => 0.0,
            'comanda' => (string) $row->numero,
            'factura' => $factura,
            'orden' => $orden,
        ];
    }
}
