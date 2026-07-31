<?php

namespace App\Http\Controllers;

use App\Models\Producto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Imagick\Driver;

class ProductoController extends Controller
{
    // App/Http/Controllers/CompraController.php
    public function historialComprasVentas($productoId)
    {
        $detalles = \App\Models\CompraDetalle::with(['compra' => function($q){
            $q->select('id','agencia');
        }])
            ->where('producto_id', $productoId)
            ->where('estado', 'Activo')
            ->whereNull('deleted_at')
            ->where('cantidad_venta', '>', 0)
            ->orderByRaw("CASE WHEN fecha_vencimiento IS NULL THEN 1 ELSE 0 END, fecha_vencimiento ASC")
            ->get(['id','compra_id','producto_id','lote','fecha_vencimiento','cantidad','cantidad_venta','precio','factor','precio_venta','nro_factura']);

        $response = $detalles->map(function($d){
            return [
                'id'               => $d->id,
                'compra_id'        => $d->compra_id,
                'agencia'          => $d->compra?->agencia,
                'producto_id'      => $d->producto_id,
                'lote'             => $d->lote,
                'fecha_vencimiento'=> $d->fecha_vencimiento,
                'cantidad'         => (float)$d->cantidad,
                'disponible'       => (float)$d->cantidad_venta,
                'precio'           => (float)$d->precio,
                'factor'           => (float)$d->factor,
                'precio_venta'     => (float)$d->precio_venta,
                'nro_factura'      => $d->nro_factura,
            ];
        });

        return response()->json($response);
    }

    function productosStock(Request $request)
    {
        $search  = trim($request->input('search', ''));
        $perPage = (int) $request->input('per_page', 36);

        $palabrasStock = array_filter(explode(' ', $search));

        $productos = Producto::query()
            ->withSum(
                ['comprasDetalles as stock' => function ($q) {
                    $q->where('estado', 'Activo');
                }],
                'cantidad_venta'
            )
            ->whereHas('comprasDetalles', function ($q) {
                $q->where('estado', 'Activo')->where('cantidad_venta', '>', 0);
            })
            ->when(count($palabrasStock) > 0, function ($q) use ($palabrasStock) {
                foreach ($palabrasStock as $palabra) {
                    $q->where(function ($qq) use ($palabra) {
                        $qq->where('productos.nombre', 'like', "%{$palabra}%")
                            ->orWhere('productos.descripcion', 'like', "%{$palabra}%")
                            ->orWhere('productos.barra', 'like', "%{$palabra}%");
                    });
                }
            })
            ->orderBy('productos.nombre')
            ->paginate($perPage);

        return response()->json($productos);
    }

    function productosAll()
    {
        return Producto::orderBy('nombre')->get();
    }

    public function index(Request $request)
    {
        $search  = $request->search;
        $perPage = $request->per_page ?? 10;
        $agencia = $request->agencia;

        $palabras = $search ? array_filter(explode(' ', trim($search))) : [];

        $productos = \App\Models\Producto::query()
            ->when(count($palabras) > 0, function ($q) use ($palabras) {
                foreach ($palabras as $palabra) {
                    $q->where(function ($qq) use ($palabra) {
                        $qq->where('nombre', 'like', "%{$palabra}%")
                            ->orWhere('descripcion', 'like', "%{$palabra}%")
                            ->orWhere('barra', 'like', "%{$palabra}%");
                    });
                }
            })
            ->withSum([
                'comprasDetalles as stock_disponible' => function ($q) use ($agencia) {
                    $q->where('estado', 'Activo')
                        ->whereNull('deleted_at');
//                    if (!empty($agencia)) {
//                        $q->whereHas('compra', function ($qc) use ($agencia) {
//                            $qc->where('agencia', $agencia);
//                        });
//                    }
                }
            ], 'cantidad_venta')
            ->orderBy('nombre')
            ->paginate($perPage);

        return response()->json($productos);
    }

    function store(Request $request)
    {
        $request->validate([
            'nombre' => 'required',
            'precio' => 'required|numeric',
            'imagen' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
        ]);

        $data = $request->except('imagen');

        // Procesar imagen si existe
        if ($request->hasFile('imagen')) {
            $data['imagen'] = $this->guardarImagen($request->file('imagen'));
        }

        $producto = Producto::create($data);

        return response()->json($producto, 201);
    }

    function update(Request $request, Producto $producto)
    {
        $request->validate([
            'nombre' => 'required',
            'precio' => 'required|numeric',
            'imagen' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
        ]);

        $data = $request->except('imagen');

        // Procesar imagen si se subió una nueva
        if ($request->hasFile('imagen')) {
            // Eliminar imagen anterior si existe
            if ($producto->imagen && file_exists(public_path('images/productos/' . $producto->imagen))) {
                unlink(public_path('images/productos/' . $producto->imagen));
            }
            $data['imagen'] = $this->guardarImagen($request->file('imagen'));
        }

        $producto->update($data);

        return response()->json($producto);
    }

    function destroy(Producto $producto)
    {
        // Eliminar imagen si existe
        if ($producto->imagen && file_exists(public_path('images/' . $producto->imagen))) {
            unlink(public_path('images/' . $producto->imagen));
        }

        $producto->delete();

        return response()->json(['success' => true]);
    }

// Método para eliminar imagen específica
    public function eliminarImagen(Producto $producto)
    {
        if ($producto->imagen && file_exists(public_path('images/' . $producto->imagen))) {
            unlink(public_path('images/productos/' . $producto->imagen));
            $producto->update(['imagen' => null]);
        }

        return response()->json(['success' => true]);
    }


    public function actualizarImagen(Request $request, Producto $producto)
    {
        $request->validate([
            'imagen' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
        ]);

        if ($producto->imagen && file_exists(public_path('images/' . $producto->imagen))) {
            unlink(public_path('images/' . $producto->imagen));
        }

        $producto->update(['imagen' => $this->guardarImagen($request->file('imagen'))]);

        return response()->json($producto);
    }

    private function guardarImagen($imagen)
    {
        // Crear directorio si no existe en public/images/productos
        $directorio = public_path('images/productos');
        if (!file_exists($directorio)) {
            mkdir($directorio, 0755, true);
        }

        // Generar nombre único
        $extension = $imagen->getClientOriginalExtension();
        $nombreImagen = time() . '_' . uniqid() . '.' . $extension;

        try {
            // Determinar driver a usar
            if (extension_loaded('imagick')) {
                $manager = \Intervention\Image\ImageManager::imagick();
            } else {
                $manager = \Intervention\Image\ImageManager::gd();
            }

            // Crear imagen
            $imagenProcesada = $manager->read($imagen);

            // Redimensionar manteniendo aspecto, máximo 500px
            $imagenProcesada->scaleDown(width: 500, height: 500);

            // Guardar en public/images/productos/
            $imagenProcesada->save(
                public_path('images/' . $nombreImagen),
                quality: 85
            );

            return $nombreImagen;

        } catch (\Exception $e) {
            // Fallback: guardar imagen original
            $nombreImagen = time() . '_' . uniqid() . '.' . $extension;
            $imagen->move(public_path('images'), $nombreImagen);
            return $nombreImagen;
        }
    }

}
