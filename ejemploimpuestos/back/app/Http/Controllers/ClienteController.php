<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use Illuminate\Http\Request;

class ClienteController extends Controller{
    function searchCliente(Request $request){
        $ci = $request->nit;
        $cliente = Cliente::where('ci', $ci)->first();
        return $cliente;
    }
}
