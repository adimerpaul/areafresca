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
}
