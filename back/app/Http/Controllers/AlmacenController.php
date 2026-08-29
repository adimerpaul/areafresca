<?php

namespace App\Http\Controllers;

use App\Exports\AlmacenesExport;
use App\Exports\AlmacenRevisionExport;
use App\Models\Almacen;
use App\Models\AlmacenDetalle;
use App\Models\Configuracion;
use App\Models\Lote;
use App\Models\Producto;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

/**
 * RevisiÃ³n fÃ­sica del stock de la tienda. El documento se llena entre varias
 * personas (cada una carga productos distintos desde su celular) y al aplicarlo
 * el stock del sistema pasa a ser exactamente lo contado, guardando por lÃ­nea el
 * valor antiguo y el nuevo.
 */
class AlmacenController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizeAction($request, 'Ver Almacenes');

        return response()->json($this->filteredQuery($request)->withCount('detalles')->latest('fecha')
            ->paginate(min(max((int) $request->input('per_page', 20), 1), 200)));
    }

    public function summary(Request $request)
    {
        $this->authorizeAction($request, 'Ver Almacenes');

        return response()->json($this->summaryData($request));
    }

    /** Excel del listado, con los mismos filtros que se ven en la pantalla. */
    public function exportExcel(Request $request)
    {
        $this->authorizeAction($request, 'Ver Almacenes');
        $almacenes = $this->filteredQuery($request)->withCount('detalles')->latest('fecha')->get();

        return Excel::download(
            new AlmacenesExport($almacenes, $this->summaryData($request), $this->exportMeta($request, $this->listFilterLabels($request))),
            'almacenes_'.now()->format('Ymd_His').'.xlsx'
        );
    }

    /**
     * Excel de una revisión: detalle, lotes y avance por usuario. Lo usan tanto
     * la pantalla de llenado como la de avance, filtrando por producto, por quién
     * lo cargó y por el horario en que se registró.
     */
    public function exportRevision(Request $request, Almacen $almacen)
    {
        $this->authorizeAction($request, 'Ver Almacenes');
        $rows = $this->progressRows($almacen, $request);
        $resumen = $this->progressSummary($rows);

        if ($request->boolean('solo_diferencias')) {
            $rows = $rows->filter(fn ($detail) => abs((float) $detail->diferencia_actual) > 0.0001)->values();
        }

        $numero = preg_replace('/[^A-Za-z0-9\-]/', '', (string) ($almacen->numero ?: $almacen->id));

        return Excel::download(
            new AlmacenRevisionExport($almacen, $rows, $resumen, $this->exportMeta($request, $this->detailFilterLabels($request))),
            "revision_{$numero}_".now()->format('Ymd_His').'.xlsx'
        );
    }

    public function show(Request $request, Almacen $almacen)
    {
        $this->authorizeAction($request, 'Ver Almacenes');

        return response()->json($this->withDetails($almacen));
    }

    /** Vista de avance: cuÃ¡nto del catÃ¡logo se revisÃ³ y quÃ© diferencias aparecieron. */
    public function progress(Request $request, Almacen $almacen)
    {
        $this->authorizeAction($request, 'Ver Almacenes');
        $rows = $this->progressRows($almacen, $request);

        return response()->json(['almacen' => $almacen, 'detalles' => $rows] + $this->progressSummary($rows));
    }

    public function store(Request $request)
    {
        $this->authorizeAction($request, 'Crear Almacenes');
        $data = $request->validate([
            'descripcion' => ['nullable', 'string', 'max:255'],
            'observacion' => ['nullable', 'string', 'max:1000'],
        ]);

        $almacen = Almacen::create([
            'user_id' => $request->user()->id,
            'usuario_nombre' => $request->user()->name,
            'descripcion' => $data['descripcion'] ?? null,
            'observacion' => $data['observacion'] ?? null,
            'estado' => 'BORRADOR',
            'fecha' => now(),
        ]);
        $almacen->update(['numero' => 'A-'.str_pad((string) $almacen->id, 8, '0', STR_PAD_LEFT)]);

        return response()->json($this->withDetails($almacen->fresh()), 201);
    }

    public function update(Request $request, Almacen $almacen)
    {
        $this->authorizeAction($request, 'Editar Almacenes');
        abort_unless($almacen->editable(), 422, 'SÃ³lo se puede modificar un almacÃ©n en revisiÃ³n');
        $almacen->update($request->validate([
            'descripcion' => ['nullable', 'string', 'max:255'],
            'observacion' => ['nullable', 'string', 'max:1000'],
        ]));

        return response()->json($this->withDetails($almacen->fresh()));
    }

    /**
     * Cuenta un producto. Cada lÃ­nea se guarda sola, asÃ­ varias personas cargan
     * productos distintos en el mismo almacÃ©n sin pisarse.
     */
    public function storeDetalle(Request $request, Almacen $almacen)
    {
        $this->authorizeAction($request, 'Editar Almacenes');
        abort_unless($almacen->editable(), 422, 'Este almacÃ©n ya fue aplicado o anulado');
        $data = $this->validatedLine($request);

        $detail = DB::transaction(function () use ($request, $almacen, $data) {
            $product = Producto::findOrFail($data['producto_id']);
            $existing = $almacen->detalles()->where('producto_id', $product->id)->first();
            abort_if($existing && ! $request->boolean('reemplazar'), 409,
                "{$existing?->usuario_nombre} ya contÃ³ {$product->nombre} con ".rtrim(rtrim(number_format((float) $existing?->cantidad, 3, '.', ''), '0'), '.'));

            $payload = $this->linePayload($product, $data) + [
                'user_id' => $request->user()->id,
                'usuario_nombre' => $request->user()->name,
            ];
            $detail = $existing ? tap($existing)->update($payload) : $almacen->detalles()->create($payload);
            $this->syncConteos($detail, $data);
            $this->refreshTotals($almacen);

            return $detail;
        });

        return response()->json($detail->fresh()->load('conteos'), 201);
    }

    public function updateDetalle(Request $request, Almacen $almacen, AlmacenDetalle $detalle)
    {
        $this->authorizeAction($request, 'Editar Almacenes');
        abort_unless($almacen->editable(), 422, 'Este almacÃ©n ya fue aplicado o anulado');
        abort_unless($detalle->almacen_id === $almacen->id, 404);
        $data = $this->validatedLine($request, $detalle);

        DB::transaction(function () use ($almacen, $detalle, $data) {
            $detalle->update($this->linePayload(Producto::findOrFail($data['producto_id']), $data));
            $this->syncConteos($detalle, $data);
            $this->refreshTotals($almacen);
        });

        return response()->json($detalle->fresh()->load('conteos'));
    }

    public function destroyDetalle(Request $request, Almacen $almacen, AlmacenDetalle $detalle)
    {
        $this->authorizeAction($request, 'Editar Almacenes');
        abort_unless($almacen->editable(), 422, 'Este almacÃ©n ya fue aplicado o anulado');
        abort_unless($detalle->almacen_id === $almacen->id, 404);

        DB::transaction(function () use ($almacen, $detalle) {
            $detalle->delete();
            $this->refreshTotals($almacen);
        });

        return response()->json(['message' => 'Producto quitado de la revisiÃ³n']);
    }

    /**
     * BotÃ³n "Actualizar productos": el stock de cada producto contado pasa a ser
     * la cantidad de la revisiÃ³n. Los productos que nadie contÃ³ no se tocan.
     */
    public function apply(Request $request, Almacen $almacen)
    {
        $this->authorizeAction($request, 'Aplicar Almacenes');
        abort_unless($almacen->estado === 'BORRADOR', 422, 'Este almacÃ©n ya fue aplicado o anulado');
        abort_if($almacen->detalles()->count() === 0, 422, 'Cuente al menos un producto antes de actualizar el inventario');

        DB::transaction(function () use ($request, $almacen) {
            foreach ($almacen->detalles as $detail) {
                $product = Producto::lockForUpdate()->find($detail->producto_id);
                abort_unless($product, 422, "El producto {$detail->nombre} ya no existe; quÃ­telo de la revisiÃ³n");

                $before = round((float) $product->stock_inicial, 3);
                $counted = round((float) $detail->cantidad, 3);
                $difference = round($counted - $before, 3);
                $countedLots = $detail->conteos;

                if ($countedLots->isNotEmpty()) {
                    // Se contaron los lotes uno por uno: eso es la verdad, se reemplazan los del sistema.
                    $this->replaceLots($detail, $product, $countedLots);
                } elseif ($difference > 0.0001) {
                    Lote::create([
                        'producto_id' => $product->id,
                        'almacen_detalle_id' => $detail->id,
                        'lote' => $detail->lote,
                        'fecha_vencimiento' => $detail->fecha_vencimiento,
                        'cantidad_inicial' => $difference,
                        'cantidad_disponible' => $difference,
                    ]);
                } elseif ($difference < -0.0001) {
                    $this->consumeLots($detail->id, $product->id, abs($difference));
                }

                $product->update(['stock_inicial' => $counted]);
                $detail->update([
                    'stock_anterior' => $before,
                    'stock_nuevo' => $counted,
                    'diferencia' => $difference,
                    'total' => round($counted * (float) $detail->precio_compra, 2),
                ]);
            }

            $almacen->update([
                'estado' => 'APLICADO',
                'fecha_aplicado' => now(),
                'aplicado_por' => $request->user()->id,
                'aplicado_por_nombre' => $request->user()->name,
            ]);
            $this->refreshTotals($almacen);
        });

        return response()->json($this->withDetails($almacen->fresh()));
    }

    /** Anula: si ya estaba aplicado deshace el ajuste de cada producto. */
    public function cancel(Request $request, Almacen $almacen)
    {
        $this->authorizeAction($request, 'Anular Almacenes');
        abort_if($almacen->estado === 'ANULADO', 422, 'El almacÃ©n ya estÃ¡ anulado');

        DB::transaction(function () use ($almacen) {
            if ($almacen->estado === 'APLICADO') {
                foreach ($almacen->detalles as $detail) {
                    $difference = round((float) $detail->diferencia, 3);
                    $product = Producto::lockForUpdate()->find($detail->producto_id);

                    // Los lotes que creÃ³ la revisiÃ³n tienen que seguir intactos para poder deshacerla.
                    $created = Lote::where('almacen_detalle_id', $detail->id)->lockForUpdate()->get();
                    foreach ($created as $lot) {
                        abort_if(round((float) $lot->cantidad_inicial - (float) $lot->cantidad_disponible, 3) > 0.0001, 422,
                            "No se puede anular: ya se usÃ³ stock del lote de {$detail->nombre}");
                    }
                    abort_if($product && $difference > 0.0001 && (float) $product->stock_inicial + 0.0001 < $difference, 422,
                        "No se puede anular: el stock de {$detail->nombre} ya fue utilizado");

                    foreach ($created as $lot) {
                        $lot->delete();
                    }
                    foreach (DB::table('almacen_detalle_lotes')->where('almacen_detalle_id', $detail->id)->get() as $allocation) {
                        Lote::whereKey($allocation->lote_id)->increment('cantidad_disponible', $allocation->cantidad);
                    }
                    DB::table('almacen_detalle_lotes')->where('almacen_detalle_id', $detail->id)->delete();

                    $product?->decrement('stock_inicial', $difference);
                }
            }

            $almacen->update(['estado' => 'ANULADO']);
        });

        return response()->json($this->withDetails($almacen->fresh()));
    }

    public function destroy(Request $request, Almacen $almacen)
    {
        $this->authorizeAction($request, 'Anular Almacenes');
        abort_unless($almacen->editable(), 422, 'SÃ³lo se puede eliminar un almacÃ©n en revisiÃ³n');
        $almacen->delete();

        return response()->json(['message' => 'AlmacÃ©n eliminado']);
    }

    /**
     * Deja los lotes del producto tal cual se contaron: guarda el saldo de los lotes
     * vigentes (para poder reponerlos al anular), los pone en cero y crea uno por
     * cada lote contado. Los lotes viejos no se borran para no perder el historial
     * de ventas que los referencia.
     */
    private function replaceLots(AlmacenDetalle $detail, Producto $product, $countedLots): void
    {
        $current = Lote::where('producto_id', $product->id)->where('cantidad_disponible', '>', 0)
            ->lockForUpdate()->get();

        foreach ($current as $lot) {
            DB::table('almacen_detalle_lotes')->insert([
                'almacen_detalle_id' => $detail->id, 'lote_id' => $lot->id,
                'cantidad' => $lot->cantidad_disponible, 'created_at' => now(), 'updated_at' => now(),
            ]);
            $lot->update(['cantidad_disponible' => 0]);
        }

        foreach ($countedLots as $countedLot) {
            Lote::create([
                'producto_id' => $product->id,
                'almacen_detalle_id' => $detail->id,
                'lote' => $countedLot->lote,
                'fecha_vencimiento' => $countedLot->fecha_vencimiento,
                'cantidad_inicial' => $countedLot->cantidad,
                'cantidad_disponible' => $countedLot->cantidad,
            ]);
        }
    }

    /** Descuenta la faltante por FIFO de vencimiento, igual que una venta. */
    private function consumeLots(int $detailId, int $productId, float $quantity): void
    {
        $remaining = $quantity;
        $lots = Lote::where('producto_id', $productId)->where('cantidad_disponible', '>', 0)
            ->orderByRaw('fecha_vencimiento IS NULL')
            ->orderBy('fecha_vencimiento')->orderBy('id')->lockForUpdate()->get();

        foreach ($lots as $lot) {
            if ($remaining <= 0.0001) {
                break;
            }
            $taken = min($remaining, (float) $lot->cantidad_disponible);
            $lot->decrement('cantidad_disponible', $taken);
            DB::table('almacen_detalle_lotes')->insert([
                'almacen_detalle_id' => $detailId, 'lote_id' => $lot->id,
                'cantidad' => $taken, 'created_at' => now(), 'updated_at' => now(),
            ]);
            $remaining = round($remaining - $taken, 3);
        }
    }

    private function validatedLine(Request $request, ?AlmacenDetalle $detalle = null): array
    {
        $data = $request->validate([
            'producto_id' => [$detalle ? 'nullable' : 'required', 'integer', 'exists:productos,id'],
            'cantidad' => ['required_without:conteos', 'nullable', 'numeric', 'min:0', 'decimal:0,3'],
            'lote' => ['nullable', 'string', 'max:100'],
            'fecha_vencimiento' => ['nullable', 'date'],
            'observacion' => ['nullable', 'string', 'max:255'],
            'conteos' => ['nullable', 'array'],
            'conteos.*.lote' => ['nullable', 'string', 'max:100'],
            'conteos.*.fecha_vencimiento' => ['nullable', 'date'],
            'conteos.*.cantidad' => ['required', 'numeric', 'min:0.001', 'decimal:0,3'],
        ]);
        $data['producto_id'] ??= $detalle?->producto_id;

        return $data;
    }

    /** Con lotes cargados la cantidad del producto es la suma de ellos. */
    private function countedQuantity(array $data): float
    {
        $lots = $data['conteos'] ?? [];

        return $lots === []
            ? round((float) ($data['cantidad'] ?? 0), 3)
            : round(array_sum(array_map(fn ($lot) => (float) $lot['cantidad'], $lots)), 3);
    }

    private function linePayload(Producto $product, array $data): array
    {
        $counted = $this->countedQuantity($data);
        $lots = $data['conteos'] ?? [];

        return [
            'producto_id' => $product->id,
            'codigo' => $product->codigo,
            'nombre' => $product->nombre,
            'unidad' => $product->unidad,
            'foto' => $product->foto,
            'stock_sistema' => round((float) $product->stock_inicial, 3),
            'cantidad' => $counted,
            // Con un solo lote se copia a la cabecera de la lÃ­nea para poder mostrarla sin abrir el detalle.
            'lote' => count($lots) === 1 ? ($lots[0]['lote'] ?? null) : ($lots === [] ? ($data['lote'] ?? null) : null),
            'fecha_vencimiento' => count($lots) === 1 ? ($lots[0]['fecha_vencimiento'] ?? null) : ($lots === [] ? ($data['fecha_vencimiento'] ?? null) : null),
            'precio_compra' => $product->precio_compra,
            'total' => round($counted * (float) $product->precio_compra, 2),
            'observacion' => $data['observacion'] ?? null,
        ];
    }

    private function syncConteos(AlmacenDetalle $detalle, array $data): void
    {
        $detalle->conteos()->delete();
        foreach ($data['conteos'] ?? [] as $lot) {
            $detalle->conteos()->create([
                'lote' => $lot['lote'] ?? null,
                'fecha_vencimiento' => $lot['fecha_vencimiento'] ?? null,
                'cantidad' => round((float) $lot['cantidad'], 3),
            ]);
        }
    }

    private function refreshTotals(Almacen $almacen): void
    {
        $details = $almacen->detalles()->get();
        $almacen->update([
            'total_cantidad' => round((float) $details->sum(fn ($d) => (float) $d->cantidad), 3),
            'total_costo' => round((float) $details->sum(fn ($d) => (float) $d->total), 2),
        ]);
    }

    /** El detalle viaja con el stock actual del producto para poder mostrar la diferencia. */
    private function withDetails(Almacen $almacen): Almacen
    {
        $almacen->load(['detalles' => fn ($query) => $query->with(['producto:id,stock_inicial,unidad', 'conteos'])->orderByDesc('id')]);

        return $almacen;
    }

    private function filteredQuery(Request $request)
    {
        $query = Almacen::query();

        if ($search = trim((string) $request->input('q'))) {
            $query->where(fn ($q) => $q->where('numero', 'like', "%{$search}%")
                ->orWhere('usuario_nombre', 'like', "%{$search}%")
                ->orWhere('descripcion', 'like', "%{$search}%")
                ->orWhere('observacion', 'like', "%{$search}%"));
        }
        if ($from = $request->date('desde')) {
            $query->whereDate('fecha', '>=', $from);
        }
        if ($to = $request->date('hasta')) {
            $query->whereDate('fecha', '<=', $to);
        }
        if ($estado = trim((string) $request->input('estado'))) {
            $query->where('estado', $estado);
        }
        // Horario de creación: sirve para separar los turnos dentro del rango de fechas.
        if ($fromTime = $this->timeBound($request->input('hora_desde'), false)) {
            $query->whereTime('fecha', '>=', $fromTime);
        }
        if ($toTime = $this->timeBound($request->input('hora_hasta'), true)) {
            $query->whereTime('fecha', '<=', $toTime);
        }

        return $query;
    }

    private function summaryData(Request $request): array
    {
        $query = $this->filteredQuery($request);

        return [
            'en_revision' => (clone $query)->where('estado', 'BORRADOR')->count(),
            'aplicados' => (clone $query)->where('estado', 'APLICADO')->count(),
            'anulados' => (clone $query)->where('estado', 'ANULADO')->count(),
            'productos_revisados' => (int) AlmacenDetalle::whereIn('almacen_id', (clone $query)->select('id'))->count(),
            'diferencia_valor' => (float) AlmacenDetalle::whereIn('almacen_id', (clone $query)->where('estado', 'APLICADO')->select('id'))
                ->selectRaw('COALESCE(SUM(diferencia * precio_compra), 0) as total')->value('total'),
        ];
    }

    /**
     * Líneas de la revisión con el stock del sistema y la diferencia al día de
     * hoy. Acepta filtros por producto, por quién lo cargó y por el horario en
     * que se registró, para que el Excel salga igual a lo que se está mirando.
     */
    private function progressRows(Almacen $almacen, Request $request): Collection
    {
        $query = $almacen->detalles()->with(['producto:id,stock_inicial', 'conteos'])->orderBy('nombre');

        if ($name = trim((string) $request->input('nombre'))) {
            $query->where(fn ($q) => $q->where('nombre', 'like', "%{$name}%")
                ->orWhere('codigo', 'like', "%{$name}%")
                ->orWhere('lote', 'like', "%{$name}%"));
        }
        if ($user = trim((string) $request->input('usuario'))) {
            $query->where('usuario_nombre', 'like', "%{$user}%");
        }
        if ($from = $request->date('desde')) {
            $query->whereDate('almacen_detalles.created_at', '>=', $from);
        }
        if ($to = $request->date('hasta')) {
            $query->whereDate('almacen_detalles.created_at', '<=', $to);
        }
        if ($fromTime = $this->timeBound($request->input('hora_desde'), false)) {
            $query->whereTime('almacen_detalles.created_at', '>=', $fromTime);
        }
        if ($toTime = $this->timeBound($request->input('hora_hasta'), true)) {
            $query->whereTime('almacen_detalles.created_at', '<=', $toTime);
        }

        $applied = $almacen->estado === 'APLICADO';

        return $query->get()->map(function ($detail) use ($applied) {
            $system = $applied ? (float) $detail->stock_anterior : (float) ($detail->producto?->stock_inicial ?? $detail->stock_sistema);
            $detail->stock_actual = $system;
            $detail->diferencia_actual = round((float) $detail->cantidad - $system, 3);

            return $detail;
        })->values();
    }

    private function progressSummary(Collection $rows): array
    {
        $withDifference = $rows->filter(fn ($d) => abs((float) $d->diferencia_actual) > 0.0001);

        return [
            'total_productos' => Producto::count(),
            'revisados' => $rows->count(),
            'con_diferencia' => $withDifference->count(),
            'sin_diferencia' => $rows->count() - $withDifference->count(),
            'sobrante' => round($withDifference->sum(fn ($d) => max(0, (float) $d->diferencia_actual)), 3),
            'faltante' => round($withDifference->sum(fn ($d) => max(0, -(float) $d->diferencia_actual)), 3),
            'diferencia_valor' => round($rows->sum(fn ($d) => (float) $d->diferencia_actual * (float) $d->precio_compra), 2),
            'por_usuario' => $rows->groupBy('usuario_nombre')->map(fn ($group, $name) => [
                'usuario' => $name ?: '—', 'productos' => $group->count(),
            ])->values(),
        ];
    }

    private function exportMeta(Request $request, array $filtros): array
    {
        return [
            'empresa' => Configuracion::value('nombre_empresa') ?: 'Area Fresca',
            'usuario' => $request->user()?->name ?? '',
            'filtros' => $filtros,
        ];
    }

    private function listFilterLabels(Request $request): array
    {
        return array_filter([
            'Nombre o número' => trim((string) $request->input('q')) ?: null,
            'Estado' => ($estado = trim((string) $request->input('estado'))) ? ($estado === 'BORRADOR' ? 'EN REVISIÓN' : $estado) : null,
            'Desde' => $request->date('desde')?->format('d/m/Y'),
            'Hasta' => $request->date('hasta')?->format('d/m/Y'),
            'Horario de creación' => $this->scheduleLabel($request),
        ]);
    }

    private function detailFilterLabels(Request $request): array
    {
        return array_filter([
            'Producto' => trim((string) $request->input('nombre')) ?: null,
            'Contó' => trim((string) $request->input('usuario')) ?: null,
            'Desde' => $request->date('desde')?->format('d/m/Y'),
            'Hasta' => $request->date('hasta')?->format('d/m/Y'),
            'Horario de creación' => $this->scheduleLabel($request),
            'Mostrando' => $request->boolean('solo_diferencias') ? 'sólo productos con diferencia' : null,
        ]);
    }

    private function scheduleLabel(Request $request): ?string
    {
        $from = $this->timeBound($request->input('hora_desde'), false);
        $to = $this->timeBound($request->input('hora_hasta'), true);
        if (! $from && ! $to) {
            return null;
        }
        $short = fn (?string $time) => $time ? substr($time, 0, 5) : null;

        return match (true) {
            $from && $to => $short($from).' a '.$short($to),
            (bool) $from => 'desde las '.$short($from),
            default => 'hasta las '.$short($to),
        };
    }

    /** 'HH:MM' del formulario a 'HH:MM:SS'; el límite superior incluye el minuto entero. */
    private function timeBound($value, bool $end): ?string
    {
        $value = trim((string) $value);
        if ($value === '' || ! preg_match('/^(\d{1,2}):(\d{2})/', $value, $parts)) {
            return null;
        }

        return sprintf('%02d:%02d:%02d', min((int) $parts[1], 23), min((int) $parts[2], 59), $end ? 59 : 0);
    }

    private function authorizeAction(Request $request, string $permission): void
    {
        abort_unless($request->user()?->hasPermissionTo($permission), 403, 'No tiene permiso para realizar esta acciÃ³n');
    }
}
