<?php

namespace App\Http\Controllers;

use App\Exports\VentasExport;
use App\Models\Producto;
use App\Models\Cliente;
use App\Models\Lote;
use App\Models\User;
use App\Models\Venta;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use App\Services\Siat\ElectronicInvoiceService;
use App\Services\Siat\SiatService;
use App\Services\InvoiceDeliveryService;

class VentaController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizeAction($request, 'Ver Ventas');
        $query = $this->filteredQuery($request)
            ->with('usuario:id,name,username')
            ->withCount('detalles')
            ->latest('fecha');

        $perPage = (int) $request->input('per_page', 50);

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
            'usuarios' => User::orderBy('username')->get(['id', 'name', 'username']),
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

        return response()->json($venta->load(['detalles', 'usuario:id,name,username']));
    }

    public function siatStatus(Request $request, SiatService $siat)
    {
        $this->authorizeAction($request, 'Ver Ventas');

        return response()->json($siat->status());
    }

    public function siatCancellationReasons(Request $request, SiatService $siat)
    {
        $this->authorizeAction($request, 'Ver Ventas');

        return response()->json($siat->cancellationReasons());
    }

    public function verifyTaxes(Request $request, Venta $venta, SiatService $siat)
    {
        $this->authorizeAction($request, 'Ver Ventas');

        return response()->json($siat->verifyInvoice($venta));
    }

    public function store(Request $request, ElectronicInvoiceService $invoices, SiatService $siat)
    {
        error_log('[VENTA][STORE] Inicio: '.json_encode(['usuario_id'=>$request->user()?->id,'cantidad_detalles'=>count($request->input('detalles',[])),'numero_documento'=>$request->input('numero_documento','0')], JSON_UNESCAPED_UNICODE));
        $this->authorizeAction($request, 'Crear Ventas');
        $data = $request->validate([
            'descuento' => ['nullable', 'numeric', 'min:0'],
            'tipo_pago' => ['required', 'in:EFECTIVO,QR,COMBINADO'],
            'monto_efectivo' => ['nullable', 'numeric', 'min:0'],
            'monto_qr' => ['nullable', 'numeric', 'min:0'],
            'observacion' => ['nullable', 'string', 'max:1000'],
            'tipo_documento' => ['nullable', 'in:CI,NIT'],
            'numero_documento' => ['nullable', 'string', 'max:30'],
            'complemento' => ['nullable', 'string', 'max:10'],
            'cliente_nombre' => ['nullable', 'string', 'max:255'],
            'cliente_email' => ['nullable', 'email', 'max:255'],
            'cliente_telefono' => ['nullable', 'string', 'max:80'],
            'cliente_direccion' => ['nullable', 'string', 'max:255'],
            'detalles' => ['required', 'array', 'min:1'],
            'detalles.*.producto_id' => ['required', 'integer', 'exists:productos,id'],
            'detalles.*.cantidad' => ['required', 'numeric', 'min:0.001', 'decimal:0,3'],
            'detalles.*.precio_venta' => ['required', 'numeric', 'min:0'],
        ]);
        $requestedDocument = trim((string) ($data['numero_documento'] ?? '0')) ?: '0';
        abort_if($requestedDocument !== '0' && empty($data['cliente_nombre']), 422, 'El nombre o razón social del cliente es obligatorio');
        if ($requestedDocument !== '0') {
            $credentials = $siat->localCredentialsStatus();
            $missing = array_values(array_filter([
                $credentials['cuis'] ? null : 'CUIS',
                $credentials['cufd'] ? null : 'CUFD',
            ]));
            abort_if($missing, 422, 'No hay '.implode(' ni ', $missing).' vigentes. Genérelos en Firma digital antes de emitir una factura.');
        }

        $venta = DB::transaction(function () use ($request, $data) {
            $items = [];
            $subtotal = 0;
            foreach ($data['detalles'] as $detail) {
                $product = Producto::lockForUpdate()->findOrFail($detail['producto_id']);
                $quantity = round((float) $detail['cantidad'], 3);
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

            $documentNumber = trim((string) ($data['numero_documento'] ?? '0')) ?: '0';
            $client = null;
            if ($documentNumber !== '0') {
                $client = Cliente::updateOrCreate(
                    ['tipo_documento' => $data['tipo_documento'] ?? 'CI', 'numero_documento' => $documentNumber],
                    [
                        'complemento' => $data['complemento'] ?? null,
                        'nombre' => $data['cliente_nombre'],
                        'email' => $data['cliente_email'] ?? null,
                        'telefono' => $data['cliente_telefono'] ?? null,
                        'direccion' => $data['cliente_direccion'] ?? null,
                    ]
                );
            }

            $sale = Venta::create([
                'user_id' => $request->user()->id,
                'cliente_id' => $client?->id,
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
                'tipo_documento' => $data['tipo_documento'] ?? 'CI',
                'numero_documento' => $documentNumber,
                'complemento' => $data['complemento'] ?? null,
                'cliente_nombre' => $data['cliente_nombre'] ?? null,
                'cliente_email' => $data['cliente_email'] ?? null,
                'tipo_comprobante' => $documentNumber === '0' ? 'RECIBO' : 'FACTURA',
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
                $stockToDeduct = min($quantity, max(0, (float) $product->stock_inicial));
                if ($stockToDeduct > 0) {
                    $product->decrement('stock_inicial', $stockToDeduct);
                }
                $remaining = $stockToDeduct;
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

        $venta->load(['detalles', 'cliente']);
        error_log('[VENTA][STORE] Venta guardada: '.json_encode(['venta_id'=>$venta->id,'numero'=>$venta->numero,'tipo_comprobante'=>$venta->tipo_comprobante,'total'=>$venta->total], JSON_UNESCAPED_UNICODE));
        if ($venta->tipo_comprobante === 'FACTURA') {
            error_log('[VENTA][STORE] Enviando venta a facturación electrónica: '.$venta->id);
            $venta = $invoices->issue($venta)->load(['detalles', 'cliente']);
        } else {
            error_log('[VENTA][STORE] No se envía a SIAT porque es recibo: '.$venta->id);
        }
        error_log('[VENTA][STORE] Fin: '.json_encode(['venta_id'=>$venta->id,'estado_siat'=>$venta->estado_siat,'mensaje_siat'=>$venta->siat_mensaje], JSON_UNESCAPED_UNICODE));
        return response()->json($venta, 201);
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
        $timeFrom = (string) $request->input('hora_desde', '');
        if (preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $timeFrom)) {
            $query->whereTime('fecha', '>=', $timeFrom.':00');
        }
        $timeTo = (string) $request->input('hora_hasta', '');
        if (preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $timeTo)) {
            $query->whereTime('fecha', '<=', $timeTo.':59');
        }
        if ($userId = $request->integer('user_id')) {
            $query->where('user_id', $userId);
        }
        if ($request->input('envio') === 'pendientes') {
            $query->where('tipo_comprobante', 'FACTURA')->where('online', false)
                ->where('estado_siat', 'PENDIENTE_EVENTO')->whereNotNull('cuf')
                ->whereNotNull('xml_path')->whereNotNull('fecha_emision_siat');
        } elseif ($request->input('envio') === 'enviadas') {
            $query->where('tipo_comprobante', 'FACTURA')->where('online', true);
        }

        return $query;
    }

    public function cancel(Request $request, Venta $venta, SiatService $siat, InvoiceDeliveryService $delivery)
    {
        $this->authorizeAction($request, 'Anular Ventas');
        abort_if($venta->estado === 'ANULADA', 422, 'La venta ya está anulada');

        $mustCancelInSiat = $venta->tipo_comprobante === 'FACTURA'
            && $venta->estado_siat === 'VALIDADA';

        if ($mustCancelInSiat) {
            $data = $request->validate([
                'codigo_motivo' => [
                    'required',
                    'integer',
                    'in:'.implode(',', array_keys(SiatService::CANCELLATION_REASONS)),
                ],
            ]);
            $siatResponse = $siat->cancelInvoice($venta, (int) $data['codigo_motivo']);

            abort_unless(
                $siatResponse['transaccion'],
                422,
                $siatResponse['mensaje'] ?: 'Impuestos rechazó la anulación de la factura',
            );
        }

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

        if ($venta->tipo_comprobante === 'FACTURA') {
            $reason = isset($data['codigo_motivo'])
                ? SiatService::CANCELLATION_REASONS[(int) $data['codigo_motivo']]
                : null;
            try {
                $delivery->sendCancellation($venta->fresh(), $reason);
            } catch (\Throwable $exception) {
                $venta->update(['email_error' => $exception->getMessage()]);
                error_log('[CORREO][ANULACION] ERROR: '.json_encode(['venta_id' => $venta->id, 'error' => $exception->getMessage()], JSON_UNESCAPED_UNICODE));
                report($exception);
            }
        }

        return response()->json([
            'venta' => $venta->fresh(),
            'mensaje' => $mustCancelInSiat
                ? 'Factura anulada en Impuestos y venta anulada correctamente'
                : 'Venta anulada correctamente',
        ]);
    }

    private function authorizeAction(Request $request, string $permission): void
    {
        abort_unless($request->user()?->hasPermissionTo($permission), 403, 'No tiene permiso para realizar esta acción');
    }
}
