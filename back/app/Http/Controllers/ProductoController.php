<?php

namespace App\Http\Controllers;

use App\Models\Categoria;
use App\Models\Producto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\Rule;

class ProductoController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizeAction($request, 'Ver Productos');
        $query = Producto::with('categoriaRelacion:id,nombre,color')->orderBy('nombre');

        if ($search = trim((string) $request->input('q'))) {
            $query->where(fn ($q) => $q->where('codigo', 'like', "%{$search}%")
                ->orWhere('nombre', 'like', "%{$search}%")
                ->orWhere('codigo_barras', 'like', "%{$search}%")
                ->orWhere('categoria', 'like', "%{$search}%"));
        }
        if ($categoriaId = $request->integer('categoria_id')) {
            $query->where('categoria_id', $categoriaId);
        }

        $perPage = (int) $request->input('per_page', 20);

        return response()->json($query->paginate($perPage === 0 ? 500 : min(max($perPage, 1), 500)));
    }

    public function catalogos(Request $request)
    {
        $this->authorizeAction($request, 'Ver Productos');

        return response()->json([
            'categorias' => Categoria::orderBy('nombre')->get(['id', 'nombre', 'color']),
            'unidades' => Producto::whereNotNull('unidad')->distinct()->orderBy('unidad')->pluck('unidad'),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizeAction($request, 'Crear Productos');

        return response()->json(Producto::create($this->validatedData($request)), 201);
    }

    public function storeCategoria(Request $request)
    {
        $this->authorizeAction($request, 'Crear Productos');
        $data = $request->validate(['nombre' => ['required', 'string', 'max:100'], 'color' => ['nullable', 'string', 'max:30']]);
        $data['nombre'] = mb_strtoupper(trim($data['nombre']));

        return response()->json(Categoria::create($data), 201);
    }

    public function updateCategoria(Request $request, Categoria $categoria)
    {
        $this->authorizeAction($request, 'Editar Productos');
        $data = $request->validate(['nombre' => ['required', 'string', 'max:100'], 'color' => ['nullable', 'string', 'max:30']]);
        $data['nombre'] = mb_strtoupper(trim($data['nombre']));
        $categoria->update($data);
        Producto::where('categoria_id', $categoria->id)->update(['categoria' => $categoria->nombre]);

        return response()->json($categoria->fresh());
    }

    public function destroyCategoria(Request $request, Categoria $categoria)
    {
        $this->authorizeAction($request, 'Eliminar Productos');
        abort_if($categoria->productos()->exists(), 422, 'No se puede eliminar una categoría que tiene productos');
        $categoria->delete();

        return response()->json(['message' => 'Categoría eliminada']);
    }

    public function updateBarcode(Request $request, Producto $producto)
    {
        $this->authorizeAction($request, 'Editar Productos');
        $data = $request->validate([
            'codigo_barras' => ['nullable', 'string', 'max:100', Rule::unique('productos')->whereNull('deleted_at')->ignore($producto)],
        ]);
        $producto->update($data);

        return response()->json($producto->fresh());
    }

    public function update(Request $request, Producto $producto)
    {
        $this->authorizeAction($request, 'Editar Productos');
        $producto->update($this->validatedData($request, $producto));

        return response()->json($producto->fresh());
    }

    public function destroy(Request $request, Producto $producto)
    {
        $this->authorizeAction($request, 'Eliminar Productos');
        $this->deletePhoto($producto);
        $producto->delete();

        return response()->json(['message' => 'Producto eliminado correctamente']);
    }

    private function validatedData(Request $request, ?Producto $producto = null): array
    {
        $data = $request->validate([
            'codigo' => ['required', 'string', 'max:50', Rule::unique('productos')->whereNull('deleted_at')->ignore($producto)],
            'codigo_barras' => ['nullable', 'string', 'max:100', Rule::unique('productos')->whereNull('deleted_at')->ignore($producto)],
            'nombre' => ['required', 'string', 'max:255'],
            'categoria' => ['nullable', 'string', 'max:100'],
            'categoria_id' => ['nullable', 'exists:categorias,id'],
            'unidad' => ['required', 'string', 'max:20'],
            'precio_compra' => ['required', 'numeric', 'min:0'],
            'precio_venta' => ['required', 'numeric', 'min:0'],
            'stock_inicial' => ['required', 'numeric', 'min:0', 'decimal:0,3'],
        ]);
        foreach (['codigo', 'nombre', 'categoria', 'unidad'] as $field) {
            $data[$field] = isset($data[$field]) && $data[$field] !== null
                ? mb_strtoupper(trim($data[$field])) : null;
        }
        if (! empty($data['categoria_id'])) {
            $data['categoria'] = Categoria::find($data['categoria_id'])?->nombre;
        }

        return $data;
    }

    public function uploadPhoto(Request $request, Producto $producto)
    {
        $this->authorizeAction($request, 'Editar Productos');
        $request->validate(['foto' => ['required', 'image', 'max:8192']]);

        $file = $request->file('foto');

        return $this->savePhoto($producto, file_get_contents($file->getPathname()));
    }

    public function uploadPhotoFromUrl(Request $request, Producto $producto)
    {
        $this->authorizeAction($request, 'Editar Productos');
        $data = $request->validate(['url' => ['required', 'url:http,https', 'max:2048']]);
        $host = parse_url($data['url'], PHP_URL_HOST);
        abort_unless($host && $this->isPublicHost($host), 422, 'La dirección de imagen no está permitida');

        $response = Http::timeout(12)->connectTimeout(5)
            ->withHeaders(['User-Agent' => 'AreaFresca/1.0'])
            ->get($data['url']);
        abort_unless($response->successful(), 422, 'No se pudo descargar la imagen');
        abort_unless(str_starts_with(strtolower($response->header('Content-Type', '')), 'image/'), 422, 'La dirección no corresponde a una imagen');
        abort_if(strlen($response->body()) > 8 * 1024 * 1024, 422, 'La imagen supera el máximo de 8 MB');

        return $this->savePhoto($producto, $response->body());
    }

    private function savePhoto(Producto $producto, string $contents)
    {
        $this->deletePhoto($producto);
        $image = imagecreatefromstring($contents);
        abort_unless($image, 422, 'No se pudo procesar la fotografía');

        $width = imagesx($image);
        $height = imagesy($image);
        $scale = min(1, 700 / max($width, $height));
        $newWidth = max(1, (int) round($width * $scale));
        $newHeight = max(1, (int) round($height * $scale));
        $output = imagecreatetruecolor($newWidth, $newHeight);
        $white = imagecolorallocate($output, 255, 255, 255);
        imagefill($output, 0, 0, $white);
        imagecopyresampled($output, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        $directory = public_path('images/productos');
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
        $filename = "producto_{$producto->id}_".time().'.webp';
        imagewebp($output, "{$directory}/{$filename}", 85);
        imagedestroy($image);
        imagedestroy($output);

        $producto->update(['foto' => "productos/{$filename}"]);

        return response()->json($producto->fresh());
    }

    private function isPublicHost(string $host): bool
    {
        $addresses = gethostbynamel($host) ?: [];
        if ($addresses === []) {
            return false;
        }

        foreach ($addresses as $address) {
            if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                return false;
            }
        }

        return true;
    }

    private function deletePhoto(Producto $producto): void
    {
        if ($producto->foto) {
            $path = public_path('images/'.$producto->foto);
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    private function authorizeAction(Request $request, string $permission): void
    {
        abort_unless($request->user()?->hasPermissionTo($permission), 403, 'No tiene permiso para realizar esta acción');
    }
}
