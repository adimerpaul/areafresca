<?php

namespace App\Http\Controllers;

use App\Models\SiatEventoSignificativo;
use App\Services\Siat\SignificantEventService;
use Illuminate\Http\Request;

class SiatEventoSignificativoController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()?->hasPermissionTo('Ver Ventas'), 403);
        return response()->json(['motivos' => collect(SignificantEventService::REASONS)->map(fn ($description, $code) => ['codigo' => $code, 'descripcion' => $description])->values(), 'eventos' => SiatEventoSignificativo::latest()->limit(20)->get()]);
    }

    public function store(Request $request, SignificantEventService $service)
    {
        abort_unless($request->user()?->hasPermissionTo('Crear Ventas'), 403);
        $data = $request->validate([
            'codigo_motivo' => ['required', 'integer', 'in:'.implode(',', array_keys(SignificantEventService::REASONS))],
            'descripcion' => ['required', 'string', 'max:255'],
            'fecha_inicio' => ['required', 'date'],
            'fecha_fin' => ['required', 'date', 'after_or_equal:fecha_inicio'],
            'venta_id' => ['nullable', 'integer', 'exists:ventas,id'],
        ]);
        return response()->json($service->process((int) $data['codigo_motivo'], $data['descripcion'], $data['fecha_inicio'], $data['fecha_fin'], $data['venta_id'] ?? null), 201);
    }
}
