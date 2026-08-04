<?php

namespace App\Http\Controllers;

use App\Models\Baja;
use App\Models\BajaMotivo;
use App\Models\Lote;
use App\Models\Producto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BajaController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizeAction($request, 'Ver Bajas');

        return response()->json($this->filteredQuery($request)->withCount('detalles')->latest('fecha')
            ->paginate(min(max((int) $request->input('per_page', 20), 1), 200)));
    }

    public function summary(Request $request)
    {
        $this->authorizeAction($request, 'Ver Bajas');
        $query = $this->filteredQuery($request)->where('estado', 'REGISTRADA');

        return response()->json([
            'costo' => (clone $query)->sum('total_costo'),
            'cantidad' => (clone $query)->count(),
            'por_motivo' => (clone $query)->selectRaw('motivo as nombre, COUNT(*) as cantidad, SUM(total_costo) as costo')
                ->groupBy('motivo')->orderByDesc('costo')->get(),
        ]);
    }

    public function catalogos(Request $request)
    {
        $this->authorizeAction($request, 'Ver Bajas');

        return response()->json(['motivos' => BajaMotivo::activos()->get(['id', 'codigo', 'nombre', 'color'])]);
    }

    /** Lotes con saldo de un producto, para bajas de vencidos donde importa cuÃ¡l lote se descarta. */
    public function lotes(Request $request)
    {
        $this->authorizeAction($request, 'Ver Bajas');
        $productoId = $request->integer('producto_id');

        return response()->json(Lote::where('producto_id', $productoId)->where('cantidad_disponible', '>', 0)
            ->orderByRaw('fecha_vencimiento IS NULL')->orderBy('fecha_vencimiento')->orderBy('id')
            ->get(['id', 'lote', 'fecha_vencimiento', 'cantidad_disponible']));
    }

    public function show(Request $request, Baja $baja)
    {
        $this->authorizeAction($request, 'Ver Bajas');

        return response()->json($baja->load('detalles'));
    }

    public function store(Request $request)
    {
        $this->authorizeAction($request, 'Crear Bajas');
        $data = $request->validate([
            'motivo_id' => ['required', 'integer', 'exists:baja_motivos,id'],
            'observacion' => ['nullable', 'string', 'max:1000'],
            'detalles' => ['required', 'array', 'min:1'],
            'detalles.*.producto_id' => ['required', 'integer', 'exists:productos,id'],
            'detalles.*.cantidad' => ['required', 'numeric', 'min:0.001', 'decimal:0,3'],
            'detalles.*.lote_id' => ['nullable', 'integer', 'exists:lotes,id'],
            'detalles.*.observacion' => ['nullable', 'string', 'max:255'],
        ]);

        $baja = DB::transaction(function () use ($request, $data) {
            $motivo = BajaMotivo::findOrFail($data['motivo_id']);
            abort_unless($motivo->activo, 422, 'El motivo seleccionado estÃ¡ desactivado');

            $items = [];
            $totalCost = 0;
            $requestedByProduct = [];
            foreach ($data['detalles'] as $detail) {
                $product = Producto::lockForUpdate()->findOrFail($detail['producto_id']);
                $quantity = round((float) $detail['cantidad'], 3);
                $requestedByProduct[$product->id] = round(($requestedByProduct[$product->id] ?? 0) + $quantity, 3);
                abort_if((float) $product->stock_inicial + 0.0001 < $requestedByProduct[$product->id], 422,
                    "Stock insuficiente para dar de baja {$product->nombre}");
                $lineCost = round((float) $product->precio_compra * $quantity, 2);
                $totalCost += $lineCost;
                $items[] = [$product, $quantity, $lineCost, $detail['lote_id'] ?? null, $detail['observacion'] ?? null];
            }

            $baja = Baja::create([
                'user_id' => $request->user()->id,
                'usuario_nombre' => $request->user()->name,
                'motivo_id' => $motivo->id,
                'motivo' => $motivo->nombre,
                'total_costo' => round($totalCost, 2),
                'estado' => 'REGISTRADA',
                'observacion' => $data['observacion'] ?? null,
                'fecha' => now(),
            ]);
            $baja->update(['numero' => 'B-'.str_pad((string) $baja->id, 8, '0', STR_PAD_LEFT)]);

            foreach ($items as [$product, $quantity, $lineCost, $lotId, $lineNote]) {
                $detail = $baja->detalles()->create([
                    'producto_id' => $product->id,
                    'codigo' => $product->codigo,
                    'nombre' => $product->nombre,
                    'unidad' => $product->unidad,
                    'foto' => $product->foto,
                    'cantidad' => $quantity,
                    'precio_compra' => $product->precio_compra,
                    'total' => $lineCost,
                    'observacion' => $lineNote,
                ]);
                $product->decrement('stock_inicial', $quantity);
                $this->consumeLots($detail->id, $product->id, $quantity, $lotId);
            }

            return $baja;
        });

        return response()->json($baja->load('detalles'), 201);
    }

    public function cancel(Request $request, Baja $baja)
    {
        $this->authorizeAction($request, 'Anular Bajas');
        abort_if($baja->estado === 'ANULADA', 422, 'La baja ya estÃ¡ anulada');

        DB::transaction(function () use ($baja) {
            foreach ($baja->detalles as $detail) {
                Producto::whereKey($detail->producto_id)->increment('stock_inicial', $detail->cantidad);
                foreach (DB::table('baja_detalle_lotes')->where('baja_detalle_id', $detail->id)->get() as $allocation) {
                    Lote::whereKey($allocation->lote_id)->increment('cantidad_disponible', $allocation->cantidad);
                }
            }
            $baja->update(['estado' => 'ANULADA']);
        });

        return response()->json($baja->fresh()->load('detalles'));
    }

    /**
     * Descuenta de los lotes: el indicado si se eligiÃ³ uno, y el resto por FIFO
     * segÃºn fecha de vencimiento, igual que una venta.
     */
    private function consumeLots(int $detailId, int $productId, float $quantity, ?int $lotId): void
    {
        $remaining = $quantity;

        $query = Lote::where('producto_id', $productId)->where('cantidad_disponible', '>', 0);
        if ($lotId) {
            $query->orderByRaw('id = ? DESC', [$lotId]);
        }
        $lots = $query->orderByRaw('fecha_vencimiento IS NULL')
            ->orderBy('fecha_vencimiento')->orderBy('id')->lockForUpdate()->get();

        foreach ($lots as $lot) {
            if ($remaining <= 0.0001) {
                break;
            }
            $taken = min($remaining, (float) $lot->cantidad_disponible);
            $lot->decrement('cantidad_disponible', $taken);
            DB::table('baja_detalle_lotes')->insert([
                'baja_detalle_id' => $detailId, 'lote_id' => $lot->id,
                'cantidad' => $taken, 'created_at' => now(), 'updated_at' => now(),
            ]);
            $remaining = round($remaining - $taken, 3);
        }
    }

    private function filteredQuery(Request $request)
    {
        $query = Baja::query();

        if ($search = trim((string) $request->input('q'))) {
            $query->where(fn ($q) => $q->where('numero', 'like', "%{$search}%")
                ->orWhere('usuario_nombre', 'like', "%{$search}%")
                ->orWhere('motivo', 'like', "%{$search}%")
                ->orWhere('observacion', 'like', "%{$search}%"));
        }
        if ($from = $request->date('desde')) {
            $query->whereDate('fecha', '>=', $from);
        }
        if ($to = $request->date('hasta')) {
            $query->whereDate('fecha', '<=', $to);
        }
        if ($motivoId = $request->integer('motivo_id')) {
            $query->where('motivo_id', $motivoId);
        }
        if ($estado = trim((string) $request->input('estado'))) {
            $query->where('estado', $estado);
        }

        return $query;
    }

    private function authorizeAction(Request $request, string $permission): void
    {
        abort_unless($request->user()?->hasPermissionTo($permission), 403, 'No tiene permiso para realizar esta acciÃ³n');
    }
}
