<?php

namespace App\Http\Controllers;

use App\Models\Compra;
use App\Models\CompraDetalle;
use App\Models\Producto;
use App\Models\Proveedor;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CompraController extends Controller{
    function historialCompras($id){
        $historial = \App\Models\CompraDetalle::with('compra')
            ->where('producto_id', $id)
            ->orderByDesc('fecha_vencimiento')
            ->get();

        return response()->json($historial);
    }
    public function productosPorVencer(Request $request){
        $dias = (int) ($request->dias ?? 5);
        $perPage = (int) ($request->perPage ?? 10); // cantidad por página
        $page = (int) ($request->page ?? 1);        // página actual

        $hoy = Carbon::now();
        $limite = $hoy->copy()->addDays($dias);

        $productos = \App\Models\CompraDetalle::with('producto')
            ->whereNotNull('fecha_vencimiento')
            ->whereBetween('fecha_vencimiento', [$hoy->format('Y-m-d'), $limite->format('Y-m-d')])
            ->orderBy('fecha_vencimiento')
            ->paginate($perPage, ['*'], 'page', $page);

        return response()->json($productos);
    }

    public function productosVencidos(Request $request)
    {
        $hoy = Carbon::now()->format('Y-m-d');
        $perPage = $request->per_page ?? 10;

        $productos = \App\Models\CompraDetalle::with('producto')
            ->whereNotNull('fecha_vencimiento')
            ->where('fecha_vencimiento', '<', $hoy)
            ->orderBy('fecha_vencimiento', 'desc')
            ->paginate($perPage);

        return response()->json($productos);
    }


    public function index(Request $request){
        $query = Compra::with(['user', 'proveedor', 'compraDetalles.producto'])->orderBy('id', 'desc');

        if ($request->fechaInicio && $request->fechaFin) {
            $query->whereBetween('fecha', [$request->fechaInicio, $request->fechaFin]);
        }

        if ($request->user) {
            $query->where('user_id', $request->user);
        }

        return $query->orderByDesc('fecha')->get();
    }

    public function anular($id)
    {
        DB::beginTransaction();
        try {
            $compra = Compra::with('compraDetalles')->findOrFail($id);

            if ($compra->estado === 'Anulado') {
                return response()->json(['message' => 'La compra ya fue anulada'], 400);
            }

            foreach ($compra->compraDetalles as $detalle) {
//                Producto::where('id', $detalle->producto_id)->decrement('stock', $detalle->cantidad);
//                switch ($compra->agencia) {
//                    case 'Almacen':
//                        Producto::where('id', $detalle->producto_id)->decrement('stockAlmacen', $detalle->cantidad);
//                        break;
//                    case 'Challgua':
//                        Producto::where('id', $detalle->producto_id)->decrement('stockChallgua', $detalle->cantidad);
//                        break;
//                    case 'Socavon':
//                        Producto::where('id', $detalle->producto_id)->decrement('stockSocavon', $detalle->cantidad);
//                        break;
//                    case 'Catalina':
//                        Producto::where('id', $detalle->producto_id)->decrement('stockCatalina', $detalle->cantidad);
//                        break;
//                }

                $detalle->estado = 'Anulado';
                $detalle->save();
            }

            $compra->estado = 'Anulado';
            $compra->save();

            DB::commit();
            return response()->json(['message' => 'Compra anulada correctamente']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error al anular la compra', 'error' => $e->getMessage()], 500);
        }
    }


    public function store(Request $request)
    {
        DB::beginTransaction();

        try {
            $fecha = Carbon::now()->format('Y-m-d');
            $hora = Carbon::now()->format('H:i:s');
//            $user = $request->user();
            // Crear la compra
            $proveedor = Proveedor::find($request->proveedor_id);
//            error_log('Proveedor: ' . json_encode($proveedor));
//            error_log('Ci:' . $proveedor->ci);
            $compra = Compra::create([
                'user_id' => auth()->id(),
                'proveedor_id' => $request->proveedor_id ?? null,
                'fecha' => $fecha,
                'hora' => $hora,
                'ci' => $proveedor->ci ?? null,
                'nombre' => $proveedor->nombre ?? null,
                'estado' => 'Activo',
                'tipo_pago' => $request->tipo_pago,
                'total' => collect($request->productos)->sum(fn($p) => $p['precio'] * $p['cantidad']),
                'nro_factura' => $request->nro_factura ?? null,
                'agencia' => $request->agencia,
            ]);

            // Crear los detalles
            foreach ($request->productos as $p) {
                CompraDetalle::create([
                    'compra_id' => $compra->id,
                    'user_id' => auth()->id(),
                    'producto_id' => $p['producto_id'],
                    'proveedor_id' => $compra->proveedor_id,
                    'nombre' => $p['producto']['nombre'],
                    'precio' => $p['precio'],
                    'cantidad' => $p['cantidad'],
                    'cantidad_venta' => $p['cantidad'],
                    'total' => $p['precio'] * $p['cantidad'],
                    'factor' => $p['factor'],
                    'precio13' => $p['precio'] * 1.3,
                    'total13' => $p['precio'] * $p['cantidad'] * 1.3,
                    'precio_venta' => $p['precio_venta'],
                    'estado' => 'Activo',
                    'lote' => $p['lote'],
                    'fecha_vencimiento' => $p['fecha_vencimiento'],
                    'nro_factura' => $compra->nro_factura,
                ]);
//                switch ($request->agencia) {
//                    case 'Almacen':
//                        Producto::where('id', $p['producto_id'])->increment('stockAlmacen', $p['cantidad']);
//                        break;
//                    case 'Challgua':
//                        Producto::where('id', $p['producto_id'])->increment('stockChallgua', $p['cantidad']);
//                        break;
//                    case 'Socavon':
//                        Producto::where('id', $p['producto_id'])->increment('stockSocavon', $p['cantidad']);
//                        break;
//                    case 'Catalina':
//                        Producto::where('id', $p['producto_id'])->increment('stockCatalina', $p['cantidad']);
//                        break;
//                }
                $producto = Producto::find($p['producto_id']);
                $producto->precio = $p['precio_venta'];
                $producto->save();
            }

            DB::commit();
            $compraSearch = Compra::with(['user', 'proveedor', 'compraDetalles.producto'])->find($compra->id);
            return $compraSearch;
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error al registrar la compra', 'error' => $e->getMessage()], 500);
        }
    }

}
