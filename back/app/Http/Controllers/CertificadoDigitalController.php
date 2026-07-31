<?php

namespace App\Http\Controllers;

use App\Models\CertificadoDigital;
use App\Services\DigitalCertificateService;
use Illuminate\Http\Request;
use RuntimeException;

class CertificadoDigitalController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizeAccess($request);
        return response()->json(CertificadoDigital::query()->latest()->get());
    }

    public function store(Request $request, DigitalCertificateService $service)
    {
        $this->authorizeAccess($request);
        $data = $request->validate([
            'archivo' => ['required', 'file', 'extensions:p12,pfx', 'max:10240'],
            'contrasena' => ['required', 'string', 'max:500'],
        ]);

        try {
            $certificate = $service->import($data['archivo'], $data['contrasena'], $request->user()->id);
            return response()->json($certificate, 201);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }
    }

    public function activate(Request $request, CertificadoDigital $certificado)
    {
        $this->authorizeAccess($request);
        CertificadoDigital::query()->update(['activo' => false]);
        $certificado->update(['activo' => true]);
        return response()->json($certificado->fresh());
    }

    public function destroy(Request $request, CertificadoDigital $certificado)
    {
        $this->authorizeAccess($request);
        $certificado->delete();
        return response()->noContent();
    }

    private function authorizeAccess(Request $request): void
    {
        abort_unless($request->user()?->hasPermissionTo('Gestionar Configuración'), 403);
    }
}
