<?php

namespace App\Http\Controllers;

use App\Models\Facturacion;
use App\Services\FacturacionImporter;
use Carbon\Carbon;
use Illuminate\Http\Request;
use RuntimeException;

class FacturacionController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizeAction($request, 'Ver Facturación');

        $perPage = (int) $request->input('per_page', 20);

        return response()->json(
            $this->filteredQuery($request)
                // El número de factura es texto: ordenar primero por largo lo deja en orden numérico
                // (y funciona igual en MySQL y en SQLite, que es donde corren los tests).
                ->orderByDesc('fecha_factura')
                ->orderByRaw('LENGTH(numero_factura) DESC')
                ->orderByDesc('numero_factura')
                ->paginate(min(max($perPage, 1), 200))
        );
    }

    public function summary(Request $request)
    {
        $this->authorizeAction($request, 'Ver Facturación');
        $query = $this->filteredQuery($request);
        $validas = (clone $query)->where('estado', 'VALIDA');

        return response()->json([
            'mes' => $this->month($request)->format('Y-m'),
            'cantidad' => (clone $query)->count(),
            'validas' => (clone $validas)->count(),
            'anuladas' => (clone $query)->where('estado', '!=', 'VALIDA')->count(),
            'importe_total' => (clone $validas)->sum('importe_total'),
            'debito_fiscal' => (clone $validas)->sum('debito_fiscal'),
            // Meses con datos, para el selector del frontend.
            'meses' => Facturacion::selectRaw('SUBSTR(fecha_factura, 1, 7) as mes, COUNT(*) as cantidad')
                ->groupBy('mes')->orderByDesc('mes')->get(),
        ]);
    }

    public function show(Request $request, Facturacion $facturacion)
    {
        $this->authorizeAction($request, 'Ver Facturación');

        return response()->json($facturacion);
    }

    public function import(Request $request, FacturacionImporter $importer)
    {
        $this->authorizeAction($request, 'Importar Facturación');
        $data = $request->validate([
            // El SIAT entrega el libro dentro de un ZIP; también se acepta el XLSX suelto.
            'archivo' => ['required', 'file', 'extensions:xlsx,xls,zip', 'max:51200'],
        ]);

        try {
            $result = $importer->import(
                $data['archivo']->getRealPath(),
                $data['archivo']->getClientOriginalName(),
                $request->user()->id
            );
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        error_log('[FACTURACION][IMPORT] '.json_encode($result + ['usuario' => $request->user()->username]));

        return response()->json($result);
    }

    public function destroy(Request $request, Facturacion $facturacion)
    {
        $this->authorizeAction($request, 'Eliminar Facturación');
        $facturacion->delete();

        return response()->noContent();
    }

    private function filteredQuery(Request $request)
    {
        $month = $this->month($request);

        return Facturacion::query()
            ->whereBetween('fecha_factura', [$month->copy()->startOfMonth()->toDateString(), $month->copy()->endOfMonth()->toDateString()])
            ->when($request->filled('estado'), fn ($query) => $query->where('estado', $request->input('estado')))
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = '%'.trim($request->input('q')).'%';
                $query->where(fn ($sub) => $sub->where('numero_factura', 'like', $term)
                    ->orWhere('cuf', 'like', $term)
                    ->orWhere('nit_ci_cliente', 'like', $term)
                    ->orWhere('razon_social', 'like', $term));
            });
    }

    /** Mes del filtro `mes` (YYYY-MM); por defecto el mes anterior, que es el que ya está cerrado en el SIAT. */
    private function month(Request $request): Carbon
    {
        $value = (string) $request->input('mes', '');

        try {
            return preg_match('/^\d{4}-\d{2}$/', $value)
                ? Carbon::createFromFormat('Y-m-d', $value.'-01')->startOfMonth()
                : now()->subMonthNoOverflow()->startOfMonth();
        } catch (\Throwable) {
            return now()->subMonthNoOverflow()->startOfMonth();
        }
    }

    private function authorizeAction(Request $request, string $permission): void
    {
        abort_unless($request->user()?->hasPermissionTo($permission), 403, 'No tiene permiso para realizar esta acción');
    }
}
