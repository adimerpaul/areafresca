<?php

namespace App\Http\Controllers;

use App\Models\Compra;
use App\Models\Lote;
use App\Models\Producto;
use App\Models\Proveedor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CompraController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizeAction($request, 'Ver Compras');
        $query = Compra::withCount('detalles')->latest('fecha');
        if ($search = trim((string) $request->input('q'))) {
            $query->where(fn ($q) => $q->where('numero', 'like', "%{$search}%")
                ->orWhere('proveedor_nombre', 'like', "%{$search}%")
                ->orWhere('numero_factura', 'like', "%{$search}%"));
        }
        if ($from = $request->date('desde')) {
            $query->whereDate('fecha', '>=', $from);
        }
        if ($to = $request->date('hasta')) {
            $query->whereDate('fecha', '<=', $to);
        }

        return response()->json($query->paginate(min(max((int) $request->input('per_page', 20), 1), 100)));
    }

    public function summary(Request $request)
    {
        $this->authorizeAction($request, 'Ver Compras');
        $query = Compra::where('estado', 'COMPLETADA');
        if ($from = $request->date('desde')) $query->whereDate('fecha', '>=', $from);
        if ($to = $request->date('hasta')) $query->whereDate('fecha', '<=', $to);

        return response()->json([
            'efectivo' => (clone $query)->sum('monto_efectivo'),
            'qr' => (clone $query)->sum('monto_qr'),
            'total' => (clone $query)->sum('total'),
            'cantidad' => (clone $query)->count(),
        ]);
    }

    public function show(Request $request, Compra $compra)
    {
        $this->authorizeAction($request, 'Ver Compras');

        return response()->json($compra->load('detalles'));
    }

    public function proveedores(Request $request)
    {
        $this->authorizeAction($request, 'Ver Compras');

        return response()->json(Proveedor::orderBy('nombre')->get());
    }

    public function storeProveedor(Request $request)
    {
        $this->authorizeAction($request, 'Crear Compras');
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:150'],
            'nit' => ['nullable', 'string', 'max:30'],
            'telefono' => ['nullable', 'string', 'max:50'],
            'direccion' => ['nullable', 'string', 'max:255'],
        ]);

        return response()->json(Proveedor::create($data), 201);
    }

    public function store(Request $request)
    {
        $this->authorizeAction($request, 'Crear Compras');
        $data = $request->validate([
            'proveedor_id' => ['required', 'exists:proveedores,id'],
            'numero_factura' => ['nullable', 'string', 'max:100'],
            'tipo_pago' => ['required', 'in:EFECTIVO,QR,COMBINADO'],
            'monto_efectivo' => ['nullable', 'numeric', 'min:0'],
            'monto_qr' => ['nullable', 'numeric', 'min:0'],
            'comentario' => ['nullable', 'string', 'max:1000'],
            'detalles' => ['required', 'array', 'min:1'],
            'detalles.*.producto_id' => ['required', 'exists:productos,id'],
            'detalles.*.cantidad' => ['required', 'numeric', 'min:0.001', 'decimal:0,3'],
            'detalles.*.precio_unitario' => ['required', 'numeric', 'min:0'],
            'detalles.*.lote' => ['nullable', 'string', 'max:100'],
            'detalles.*.fecha_vencimiento' => ['nullable', 'date'],
        ]);

        $purchase = DB::transaction(function () use ($request, $data) {
            $provider = Proveedor::findOrFail($data['proveedor_id']);
            $total = collect($data['detalles'])->sum(fn ($detail) => round($detail['cantidad'] * $detail['precio_unitario'], 2));
            $cash = $data['tipo_pago'] === 'EFECTIVO' ? $total : round((float) ($data['monto_efectivo'] ?? 0), 2);
            $qr = $data['tipo_pago'] === 'QR' ? $total : round((float) ($data['monto_qr'] ?? 0), 2);
            abort_if(abs(($cash + $qr) - $total) > 0.009, 422, 'Los montos de efectivo y QR deben sumar el total de la compra');
            $purchase = Compra::create([
                'user_id' => $request->user()->id,
                'usuario_nombre' => $request->user()->name,
                'proveedor_id' => $provider->id,
                'proveedor_nombre' => $provider->nombre,
                'numero_factura' => $data['numero_factura'] ?? null,
                'tipo_pago' => $data['tipo_pago'],
                'monto_efectivo' => $cash,
                'monto_qr' => $qr,
                'comentario' => $data['comentario'] ?? null,
                'total' => $total, 'estado' => 'COMPLETADA',
                'fecha' => now(),
            ]);
            $purchase->update(['numero' => 'C-'.str_pad((string) $purchase->id, 8, '0', STR_PAD_LEFT)]);

            foreach ($data['detalles'] as $item) {
                $product = Producto::lockForUpdate()->findOrFail($item['producto_id']);
                $quantity = round((float) $item['cantidad'], 3);
                $unitPrice = round((float) $item['precio_unitario'], 4);
                $detail = $purchase->detalles()->create([
                    'producto_id' => $product->id, 'codigo' => $product->codigo,
                    'nombre' => $product->nombre, 'unidad' => $product->unidad,
                    'lote' => $item['lote'] ?? null,
                    'fecha_vencimiento' => $item['fecha_vencimiento'] ?? null,
                    'cantidad' => $quantity, 'precio_unitario' => $unitPrice,
                    'total' => round($quantity * $unitPrice, 2),
                ]);
                Lote::create([
                    'producto_id' => $product->id, 'compra_detalle_id' => $detail->id,
                    'lote' => $item['lote'] ?? null,
                    'fecha_vencimiento' => $item['fecha_vencimiento'] ?? null,
                    'cantidad_inicial' => $quantity, 'cantidad_disponible' => $quantity,
                ]);
                $product->increment('stock_inicial', $quantity);
                $product->update(['precio_compra' => $unitPrice]);
            }

            return $purchase;
        });

        return response()->json($purchase->load('detalles'), 201);
    }

    public function cancel(Request $request, Compra $compra)
    {
        $this->authorizeAction($request, 'Crear Compras');
        abort_if($compra->estado === 'ANULADA', 422, 'La compra ya está anulada');
        DB::transaction(function () use ($compra) {
            foreach ($compra->detalles as $detail) {
                $product = Producto::lockForUpdate()->find($detail->producto_id);
                abort_if($product && (float) $product->stock_inicial + 0.0001 < (float) $detail->cantidad, 422, "No se puede anular: el stock de {$detail->nombre} ya fue utilizado");
                if ($product) $product->decrement('stock_inicial', $detail->cantidad);
                Lote::where('compra_detalle_id', $detail->id)->delete();
            }
            $compra->update(['estado' => 'ANULADA']);
        });

        return response()->json($compra->fresh());
    }

    public function vencimientos(Request $request)
    {
        $this->authorizeAction($request, 'Ver Compras');
        $status = $request->input('estado', 'por_vencer');
        $days = min(max((int) $request->input('dias', 30), 1), 365);
        $query = Lote::with('producto:id,codigo,nombre,unidad')
            ->where('cantidad_disponible', '>', 0)->whereNotNull('fecha_vencimiento');
        if ($status === 'vencido') {
            $query->whereDate('fecha_vencimiento', '<', today());
        } else {
            $query->whereDate('fecha_vencimiento', '>=', today())
                ->whereDate('fecha_vencimiento', '<=', today()->addDays($days));
        }

        return response()->json($query->orderBy('fecha_vencimiento')->get()->map(function ($lot) {
            $lot->dias_vencimiento = today()->diffInDays($lot->fecha_vencimiento, false);

            return $lot;
        }));
    }

    private function authorizeAction(Request $request, string $permission): void
    {
        abort_unless($request->user()?->hasPermissionTo($permission), 403, 'No tiene permiso para realizar esta acción');
    }
}
