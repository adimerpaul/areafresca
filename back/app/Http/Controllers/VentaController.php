<?php

namespace App\Http\Controllers;

use App\Exports\VentasExport;
use App\Models\Producto;
use App\Models\Lote;
use App\Models\User;
use App\Models\Venta;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class VentaController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizeAction($request, 'Ver Ventas');
        $query = $this->filteredQuery($request)->withCount('detalles')->latest('fecha');

        $perPage = (int) $request->input('per_page', 20);

        return response()->json($query->paginate($perPage === 0 ? 500 : min(max($perPage, 1), 500)));
    }

    public function summary(Request $request)
    {
        $this->authorizeAction($request, 'Ver Ventas');
        $query = $this->filteredQuery($request)->where('estado', 'COMPLETADA');

        return response()->json([
            'efectivo' => (clone $query)->sum('monto_efectivo'),
            'qr' => (clone $query)->sum('monto_qr'),
            'total' => (clone $query)->sum('total'),
            'descuento' => (clone $query)->sum('descuento'),
            'cantidad' => (clone $query)->count(),
            'usuarios' => User::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function dashboard(Request $request)
    {
        $this->authorizeAction($request, 'Ver Ventas');
        $sales = Venta::where('estado', 'COMPLETADA');
        $total = (float) (clone $sales)->sum('total');
        $count = (clone $sales)->count();
        $items = (float) DB::table('venta_detalles')
            ->join('ventas', 'ventas.id', '=', 'venta_detalles.venta_id')
            ->where('ventas.estado', 'COMPLETADA')
            ->whereNull('ventas.deleted_at')->whereNull('venta_detalles.deleted_at')
            ->sum('venta_detalles.cantidad');
        $profit = (float) DB::table('venta_detalles')
            ->join('ventas', 'ventas.id', '=', 'venta_detalles.venta_id')
            ->where('ventas.estado', 'COMPLETADA')
            ->whereNull('ventas.deleted_at')->whereNull('venta_detalles.deleted_at')
            ->selectRaw('COALESCE(SUM(((venta_detalles.precio_venta - venta_detalles.precio_compra) * venta_detalles.cantidad) - venta_detalles.descuento), 0) AS total')
            ->value('total');

        $dailyRaw = Venta::where('estado', 'COMPLETADA')->where('fecha', '>=', now()->subDays(6)->startOfDay())
            ->selectRaw('DATE(fecha) as dia, SUM(total) as total')->groupBy('dia')->pluck('total', 'dia');
        $daily = collect(range(6, 0))->map(function ($days) use ($dailyRaw) {
            $date = now()->subDays($days);

            return ['label' => $date->format('d/m'), 'total' => (float) ($dailyRaw[$date->toDateString()] ?? 0)];
        });

        $byUser = Venta::where('estado', 'COMPLETADA')->selectRaw('usuario_nombre as nombre, SUM(total) as total')
            ->groupBy('usuario_nombre')->orderByDesc('total')->limit(8)->get();
        $payments = Venta::where('estado', 'COMPLETADA')->selectRaw('tipo_pago as nombre, SUM(total) as total')
            ->groupBy('tipo_pago')->get();
        $topProducts = DB::table('venta_detalles')->join('ventas', 'ventas.id', '=', 'venta_detalles.venta_id')
            ->where('ventas.estado', 'COMPLETADA')->whereNull('ventas.deleted_at')->whereNull('venta_detalles.deleted_at')
            ->selectRaw('venta_detalles.producto_id, venta_detalles.nombre, venta_detalles.foto, SUM(venta_detalles.cantidad) as cantidad, SUM(venta_detalles.total) as total')
            ->groupBy('venta_detalles.producto_id', 'venta_detalles.nombre', 'venta_detalles.foto')
            ->orderByDesc('cantidad')->limit(8)->get();

        return response()->json([
            'indicadores' => ['ventas' => $total, 'ganancia' => $profit, 'productos' => $items, 'cantidad_ventas' => $count, 'ticket_promedio' => $count ? $total / $count : 0],
            'diario' => $daily, 'usuarios' => $byUser, 'pagos' => $payments, 'productos_top' => $topProducts,
        ]);
    }

    public function exportExcel(Request $request)
    {
        $this->authorizeAction($request, 'Ver Ventas');

        return Excel::download(new VentasExport($this->filteredQuery($request)->latest('fecha')->get()), 'ventas_'.now()->format('Ymd_His').'.xlsx');
    }

    public function exportPdf(Request $request)
    {
        $this->authorizeAction($request, 'Ver Ventas');
        $ventas = $this->filteredQuery($request)->latest('fecha')->get();
        $valid = $ventas->where('estado', 'COMPLETADA');
        $resumen = ['efectivo' => $valid->sum('monto_efectivo'), 'qr' => $valid->sum('monto_qr'), 'total' => $valid->sum('total')];

        return Pdf::loadView('ventas.reporte', compact('ventas', 'resumen'))->setPaper('letter', 'landscape')
            ->download('ventas_'.now()->format('Ymd_His').'.pdf');
    }

    public function show(Request $request, Venta $venta)
    {
        $this->authorizeAction($request, 'Ver Ventas');

        return response()->json($venta->load('detalles'));
    }

    public function store(Request $request)
    {
        $this->authorizeAction($request, 'Crear Ventas');
        $data = $request->validate([
            'descuento' => ['nullable', 'numeric', 'min:0'],
            'tipo_pago' => ['required', 'in:EFECTIVO,QR,COMBINADO'],
            'monto_efectivo' => ['nullable', 'numeric', 'min:0'],
            'monto_qr' => ['nullable', 'numeric', 'min:0'],
            'observacion' => ['nullable', 'string', 'max:1000'],
            'detalles' => ['required', 'array', 'min:1'],
            'detalles.*.producto_id' => ['required', 'integer', 'exists:productos,id'],
            'detalles.*.cantidad' => ['required', 'numeric', 'min:0.001', 'decimal:0,3'],
            'detalles.*.precio_venta' => ['required', 'numeric', 'min:0'],
        ]);

        $venta = DB::transaction(function () use ($request, $data) {
            $items = [];
            $subtotal = 0;
            foreach ($data['detalles'] as $detail) {
                $product = Producto::lockForUpdate()->findOrFail($detail['producto_id']);
                $quantity = round((float) $detail['cantidad'], 3);
                abort_if((float) $product->stock_inicial + 0.0001 < $quantity, 422, "Stock insuficiente para {$product->nombre}");
                $salePrice = round((float) $detail['precio_venta'], 4);
                $lineSubtotal = round($salePrice * $quantity, 2);
                $subtotal += $lineSubtotal;
                $items[] = [$product, $quantity, $salePrice, $lineSubtotal];
            }

            $discount = round((float) ($data['descuento'] ?? 0), 2);
            abort_if($discount > $subtotal, 422, 'El descuento no puede superar el subtotal');
            $total = round($subtotal - $discount, 2);
            $cash = $data['tipo_pago'] === 'EFECTIVO' ? $total : round((float) ($data['monto_efectivo'] ?? 0), 2);
            $qr = $data['tipo_pago'] === 'QR' ? $total : round((float) ($data['monto_qr'] ?? 0), 2);
            abort_if(abs(($cash + $qr) - $total) > 0.009, 422, 'Los montos de efectivo y QR deben sumar el total de la venta');

            $sale = Venta::create([
                'user_id' => $request->user()->id,
                'usuario_nombre' => $request->user()->name,
                'subtotal' => $subtotal,
                'descuento' => $discount,
                'total' => $total,
                'tipo_pago' => $data['tipo_pago'],
                'monto_efectivo' => $cash,
                'monto_qr' => $qr,
                'estado' => 'COMPLETADA',
                'observacion' => $data['observacion'] ?? null,
                'fecha' => now(),
            ]);
            $sale->update(['numero' => 'V-'.str_pad((string) $sale->id, 8, '0', STR_PAD_LEFT)]);

            $allocated = 0;
            foreach ($items as $index => [$product, $quantity, $salePrice, $lineSubtotal]) {
                $lineDiscount = $index === array_key_last($items)
                    ? $discount - $allocated
                    : round($discount * ($lineSubtotal / $subtotal), 2);
                $allocated += $lineDiscount;
                $saleDetail = $sale->detalles()->create([
                    'producto_id' => $product->id,
                    'codigo' => $product->codigo,
                    'codigo_barras' => $product->codigo_barras,
                    'nombre' => $product->nombre,
                    'categoria' => $product->categoria,
                    'unidad' => $product->unidad,
                    'foto' => $product->foto,
                    'precio_compra' => $product->precio_compra,
                    'precio_venta' => $salePrice,
                    'cantidad' => $quantity,
                    'subtotal' => $lineSubtotal,
                    'descuento' => $lineDiscount,
                    'total' => $lineSubtotal - $lineDiscount,
                ]);
                $product->decrement('stock_inicial', $quantity);
                $remaining = $quantity;
                $lots = Lote::where('producto_id', $product->id)
                    ->where('cantidad_disponible', '>', 0)
                    ->orderByRaw('fecha_vencimiento IS NULL')
                    ->orderBy('fecha_vencimiento')->orderBy('id')->lockForUpdate()->get();
                foreach ($lots as $lot) {
                    if ($remaining <= 0.0001) {
                        break;
                    }
                    $taken = min($remaining, (float) $lot->cantidad_disponible);
                    $lot->decrement('cantidad_disponible', $taken);
                    DB::table('venta_detalle_lotes')->insert([
                        'venta_detalle_id' => $saleDetail->id, 'lote_id' => $lot->id,
                        'cantidad' => $taken, 'created_at' => now(), 'updated_at' => now(),
                    ]);
                    $remaining = round($remaining - $taken, 3);
                }
            }

            return $sale;
        });

        return response()->json($venta->load('detalles'), 201);
    }

    private function filteredQuery(Request $request)
    {
        $query = Venta::query();
        if ($search = trim((string) $request->input('q'))) {
            $query->where(fn ($q) => $q->where('numero', 'like', "%{$search}%")
                ->orWhere('usuario_nombre', 'like', "%{$search}%")
                ->orWhere('estado', 'like', "%{$search}%"));
        }
        if ($from = $request->date('desde')) {
            $query->whereDate('fecha', '>=', $from);
        }
        if ($to = $request->date('hasta')) {
            $query->whereDate('fecha', '<=', $to);
        }
        if ($userId = $request->integer('user_id')) {
            $query->where('user_id', $userId);
        }

        return $query;
    }

    public function cancel(Request $request, Venta $venta)
    {
        $this->authorizeAction($request, 'Anular Ventas');
        abort_if($venta->estado === 'ANULADA', 422, 'La venta ya está anulada');

        DB::transaction(function () use ($venta) {
            foreach ($venta->detalles as $detail) {
                Producto::whereKey($detail->producto_id)->increment('stock_inicial', $detail->cantidad);
                $allocations = DB::table('venta_detalle_lotes')->where('venta_detalle_id', $detail->id)->get();
                foreach ($allocations as $allocation) {
                    Lote::whereKey($allocation->lote_id)->increment('cantidad_disponible', $allocation->cantidad);
                }
            }
            $venta->update(['estado' => 'ANULADA']);
        });

        return response()->json($venta->fresh());
    }

    private function authorizeAction(Request $request, string $permission): void
    {
        abort_unless($request->user()?->hasPermissionTo($permission), 403, 'No tiene permiso para realizar esta acción');
    }
}
