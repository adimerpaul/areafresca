<?php

namespace App\Http\Controllers;

use App\Models\SiatToken;
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
