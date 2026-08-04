<?php

namespace Tests\Feature;

use App\Models\BajaMotivo;
use App\Models\Producto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BajaTest extends TestCase
{
    use RefreshDatabase;

    public function test_registering_and_cancelling_a_baja_updates_stock(): void
    {
        $admin = User::where('username', 'admin')->firstOrFail();
        Sanctum::actingAs($admin);
        $product = Producto::firstOrFail();
        $product->update(['stock_inicial' => 10, 'precio_compra' => 12.50]);
        $reason = BajaMotivo::where('codigo', 'MERMA')->firstOrFail();

        $baja = $this->postJson('/api/bajas', [
            'motivo_id' => $reason->id,
            'observacion' => 'Prueba de merma',
            'detalles' => [[
                'producto_id' => $product->id,
                'cantidad' => 2.5,
            ]],
        ])->assertCreated()
            ->assertJsonPath('estado', 'REGISTRADA')
            ->assertJsonPath('detalles.0.cantidad', '2.500')
            ->json();

        $this->assertSame(7.5, (float) $product->fresh()->stock_inicial);
        $this->assertSame(31.25, (float) $baja['total_costo']);

        $this->putJson("/api/bajas/{$baja['id']}/anular")
            ->assertOk()
            ->assertJsonPath('estado', 'ANULADA');

        $this->assertSame(10.0, (float) $product->fresh()->stock_inicial);
    }

    public function test_baja_endpoints_require_their_permissions(): void
    {
        $user = User::create([
            'name' => 'OPERADOR',
            'username' => 'operador',
            'password' => bcrypt('123456'),
        ]);
        Sanctum::actingAs($user);

        $this->getJson('/api/bajas')->assertForbidden();

        $user->givePermissionTo('Ver Bajas');
        $this->getJson('/api/bajas')->assertOk();

        $this->postJson('/api/bajas', [])->assertForbidden();
    }
}
