<?php

namespace App\Http\Controllers;

use App\Models\Configuracion;
use Illuminate\Http\Request;

class ConfiguracionController extends Controller
{
    public function show()
    {
        return response()->json(Configuracion::firstOrCreate([], ['nombre_empresa' => 'Area Fresca']));
    }

    public function update(Request $request)
    {
        abort_unless($request->user()?->hasPermissionTo('Gestionar Configuración'), 403);
        $data = $request->validate([
            'nombre_empresa' => ['required', 'string', 'max:150'],
            'nit' => ['nullable', 'string', 'max:50'],
            'direccion' => ['nullable', 'string', 'max:255'],
            'telefono' => ['nullable', 'string', 'max:80'],
        ]);
        $config = Configuracion::firstOrCreate([], ['nombre_empresa' => 'Area Fresca']);
        $config->update($data);

        return response()->json($config);
    }

    public function uploadLogo(Request $request)
    {
        abort_unless($request->user()?->hasPermissionTo('Gestionar Configuración'), 403);
        $request->validate(['logo' => ['required', 'image', 'max:8192']]);
        $config = Configuracion::firstOrCreate([], ['nombre_empresa' => 'Area Fresca']);
        if ($config->logo && is_file(public_path('images/'.$config->logo))) {
            unlink(public_path('images/'.$config->logo));
        }
        $image = imagecreatefromstring(file_get_contents($request->file('logo')->getPathname()));
        abort_unless($image, 422, 'No se pudo procesar el logotipo');
        $width = imagesx($image); $height = imagesy($image);
        $scale = min(1, 800 / max($width, $height));
        $output = imagecreatetruecolor((int) ($width * $scale), (int) ($height * $scale));
        imagealphablending($output, false); imagesavealpha($output, true);
        imagecopyresampled($output, $image, 0, 0, 0, 0, imagesx($output), imagesy($output), $width, $height);
        $directory = public_path('images/empresa');
        if (! is_dir($directory)) mkdir($directory, 0755, true);
        $filename = 'empresa/logo_'.time().'.webp';
        imagewebp($output, public_path('images/'.$filename), 88);
        imagedestroy($image); imagedestroy($output);
        $config->update(['logo' => $filename]);

        return response()->json($config->fresh());
    }
}
