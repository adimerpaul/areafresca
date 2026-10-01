<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use Illuminate\Http\Request;

class ClienteController extends Controller
{
    public function search(Request $request)
    {
        $data = $request->validate([
            'tipo_documento' => ['required', 'in:CI,NIT'],
            'numero_documento' => ['required', 'string', 'max:30'],
        ]);

        return response()->json(['cliente' => Cliente::where($data)->first()]);
    }

    // Autocompletado del nombre en el diálogo de venta: busca por nombre o número de documento.
    public function index(Request $request)
    {
        abort_unless($request->user()?->hasPermissionTo('Crear Ventas'), 403, 'No tienes permiso para buscar clientes');
        $q = trim((string) $request->query('q', ''));
        if (mb_strlen($q) < 2) {
            return response()->json([]);
        }

        return response()->json(Cliente::where(fn ($query) => $query->where('nombre', 'like', "%{$q}%")->orWhere('numero_documento', 'like', "{$q}%"))
            ->orderBy('nombre')->limit(15)
            ->get(['id', 'tipo_documento', 'numero_documento', 'complemento', 'nombre', 'email', 'telefono', 'direccion']));
    }
}
