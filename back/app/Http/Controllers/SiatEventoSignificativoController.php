<?php

namespace App\Http\Controllers;

use App\Models\SiatEventoSignificativo;
use App\Models\Venta;
use App\Services\Siat\ElectronicInvoiceService;
use App\Services\Siat\SignificantEventService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class SiatEventoSignificativoController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()?->hasPermissionTo('Ver Ventas'), 403);

        return response()->json(['motivos' => collect(SignificantEventService::REASONS)->map(fn ($description, $code) => ['codigo' => $code, 'descripcion' => $description])->values(), 'eventos' => SiatEventoSignificativo::latest()->limit(20)->get()]);
    }

    public function store(Request $request, SignificantEventService $service, ElectronicInvoiceService $invoices)
    {
        abort_unless($request->user()?->hasPermissionTo('Crear Ventas'), 403);
        $data = $request->validate([
            'codigo_motivo' => ['required', 'integer', 'in:'.implode(',', array_keys(SignificantEventService::REASONS))],
            'descripcion' => ['required', 'string', 'max:255'],
            'venta_id' => ['required', 'integer', 'exists:ventas,id'],
            'fecha_emision' => ['nullable', 'date'],
        ]);

        $venta = Venta::findOrFail($data['venta_id']);

        // Factura que se intentó mandar en línea y falló: primero se reconvierte a
        // fuera de línea (CUF y XML con tipo de emisión 2) para que el paquete sea
        // coherente con el codigoEmision que usa el evento significativo.
        if (in_array($venta->estado_siat, ElectronicInvoiceService::FAILED_SEND_STATES, true)) {
            try {
                $venta = $invoices->prepareFailedInvoiceForEvent($venta);
            } catch (\RuntimeException $exception) {
                abort(422, $exception->getMessage());
            }
        }

        if (! empty($data['fecha_emision'])) {
            abort_unless($request->user()?->hasPermissionTo('Corregir Fecha Factura SIAT'), 403, 'No tiene permiso para corregir la fecha de una factura');
            $invoices->reprepareOfflineInvoice($venta, Carbon::parse($data['fecha_emision']));
        }

        return response()->json($service->process((int) $data['codigo_motivo'], $data['descripcion'], (int) $data['venta_id']), 201);
    }
}
