<?php

namespace App\Http\Controllers;

use App\Models\SiatToken;
use App\Services\Siat\SiatService;
use Illuminate\Http\Request;

class SiatTokenController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizeAccess($request);

        return response()->json(SiatToken::query()->latest()->get());
    }

    public function store(Request $request)
    {
        $this->authorizeAccess($request);
        $data = $request->validate([
            'token' => ['required', 'string', 'max:20000'],
        ]);

        $parts = explode('.', trim($data['token']));
        abort_unless(count($parts) === 3, 422, 'El token JWT no tiene un formato valido.');
        $payload = json_decode($this->base64UrlDecode($parts[1]), true);
        abort_unless(is_array($payload) && isset($payload['exp']) && is_numeric($payload['exp']), 422, 'El token no contiene una fecha de vencimiento valida.');

        $token = SiatToken::query()->create([
            'token_cifrado' => trim($data['token']),
            'vence_en' => date('Y-m-d H:i:s', (int) $payload['exp']),
        ]);

        return response()->json($token, 201);
    }

    public function destroy(Request $request, SiatToken $token)
    {
        $this->authorizeAccess($request);
        $token->delete();

        return response()->noContent();
    }

    public function credentials(Request $request, SiatService $siat)
    {
        $this->authorizeAccess($request);

        return response()->json($siat->localCredentialsStatus());
    }

    public function createCuis(Request $request, SiatService $siat)
    {
        $this->authorizeAccess($request);
        try {
            $siat->createCuis();

            return response()->json(['message' => 'CUIS generado correctamente', 'credentials' => $siat->localCredentialsStatus()], 201);
        } catch (\Throwable $exception) {
            abort(422, $exception->getMessage());
        }
    }

    public function createCufd(Request $request, SiatService $siat)
    {
        $this->authorizeAccess($request);
        $data = $request->validate(['forzar' => ['sometimes', 'boolean']]);
        $force = (bool) ($data['forzar'] ?? false);
        if ($siat->localCredentialsStatus()['cufd'] && ! $force) {
            return response()->json([
                'message' => 'Ya existe un CUFD vigente. Confirme si desea volver a generarlo.',
                'confirmation_required' => true,
            ], 409);
        }

        try {
            $siat->createCufd($force);

            return response()->json([
                'message' => $force ? 'CUFD regenerado y guardado correctamente' : 'CUFD generado correctamente',
                'credentials' => $siat->localCredentialsStatus(),
            ], 201);
        } catch (\Throwable $exception) {
            abort(422, $exception->getMessage());
        }
    }

    private function base64UrlDecode(string $value): string
    {
        $decoded = base64_decode(strtr($value, '-_', '+/').str_repeat('=', (4 - strlen($value) % 4) % 4), true);
        abort_if($decoded === false, 422, 'El contenido del token no es valido.');

        return $decoded;
    }

    private function authorizeAccess(Request $request): void
    {
        abort_unless($request->user()?->hasPermissionTo('Gestionar Configuración'), 403);
    }
}
