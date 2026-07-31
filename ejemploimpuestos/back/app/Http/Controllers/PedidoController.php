<?php

namespace App\Http\Controllers;

use App\Models\Pedido;
use App\Models\PedidoDetalle;
use App\Models\Producto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PedidoController extends Controller {
    function recuperarPedido(Request $request) {
        $pedido = Pedido::with('detalles.producto')
            ->where('id', $request->id)
            ->first();
        return response()->json($pedido);
    }
    public function update(Request $request, Pedido $pedido) {
        DB::beginTransaction();
        try {
            $pedido->update($request->all());
            DB::commit();
            return response()->json($pedido);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
    public function index(Request $request) {
        $fechaInicio = $request->fechaInicio;
        $fechaFin = $request->fechaFin;
        return Pedido::with('detalles.producto')
            ->where('fecha', '>=', $fechaInicio)
            ->where('fecha', '<=', $fechaFin)
            ->orderBy('fecha', 'desc')
            ->orderBy('hora', 'desc')
            ->with('user')
            ->get();
    }

    public function store(Request $request) {
        DB::beginTransaction();
        try {
            $user = $request->user();

            $pedido = Pedido::create([
                'user_id' => $user->id,
                'fecha' => now()->format('Y-m-d'),
                'hora' => now()->format('H:i:s'),
                'estado' => 'Pendiente',
                'total' => 0,
                'observaciones' => $request->observaciones
            ]);

            $total = 0;
            foreach ($request->productos as $item) {
                $subtotal = $item['precio'] * $item['cantidad'];
                $pedido->detalles()->create([
                    'producto_id' => $item['producto_id'],
                    'cantidad' => $item['cantidad'],
                    'precio' => $item['precio'],
                    'total' => $subtotal
                ]);
                $total += $subtotal;
            }

            $pedido->update(['total' => $total]);

            DB::commit();
            return response()->json($pedido->load('detalles.producto'), 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
