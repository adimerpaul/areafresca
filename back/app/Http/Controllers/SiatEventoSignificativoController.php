<?php

namespace App\Http\Controllers;

use App\Models\SiatEventoSignificativo;
use App\Services\Siat\SignificantEventService;
use Illuminate\Http\Request;
use App\Models\Venta;
use App\Services\Siat\ElectronicInvoiceService;
use Carbon\Carbon;

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

        if (! empty($data['fecha_emision'])) {
            abort_unless($request->user()?->hasPermissionTo('Corregir Fecha Factura SIAT'), 403, 'No tiene permiso para corregir la fecha de una factura');
            $invoices->reprepareOfflineInvoice(Venta::findOrFail($data['venta_id']), Carbon::parse($data['fecha_emision']));
        }

        return response()->json($service->process((int) $data['codigo_motivo'], $data['descripcion'], (int) $data['venta_id']), 201);
    }
}
