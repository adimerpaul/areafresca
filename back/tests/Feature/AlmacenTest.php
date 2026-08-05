<?php

namespace Tests\Feature;

use App\Models\Almacen;
use App\Models\Lote;
use App\Models\Producto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AlmacenTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::where('username', 'admin')->firstOrFail();
        Sanctum::actingAs($admin);

        return $admin;
    }

    private function draft(): array
    {
        return $this->postJson('/api/almacenes', ['descripcion' => 'REVISIÃ“N DE PRUEBA'])
            ->assertCreated()->assertJsonPath('estado', 'BORRADOR')->json();
    }

    public function test_counted_quantity_becomes_the_official_stock(): void
    {
        $this->admin();
        $product = Producto::first();
        $product->update(['stock_inicial' => 12]);

        $almacen = $this->draft();
        $this->postJson("/api/almacenes/{$almacen['id']}/detalles", [
            'producto_id' => $product->id, 'cantidad' => 10, 'lote' => 'L-1',
        ])->assertCreated()->assertJsonPath('stock_sistema', '12.000');

        // Mientras se revisa, el stock no se toca.
        $this->assertSame(12.0, (float) $product->fresh()->stock_inicial);

        $this->postJson("/api/almacenes/{$almacen['id']}/aplicar")->assertOk()->assertJsonPath('estado', 'APLICADO');

        $this->assertSame(10.0, (float) $product->fresh()->stock_inicial);
        $detail = Almacen::find($almacen['id'])->detalles()->first();
        $this->assertSame(12.0, (float) $detail->stock_anterior);
        $this->assertSame(10.0, (float) $detail->stock_nuevo);
        $this->assertSame(-2.0, (float) $detail->diferencia);
    }

    public function test_surplus_creates_a_lot_and_shortage_consumes_lots(): void
    {
        $this->admin();
        [$low, $high] = Producto::take(2)->get()->all();
        $low->update(['stock_inicial' => 4]);
        $high->update(['stock_inicial' => 4]);
        Lote::create(['producto_id' => $high->id, 'lote' => 'VIEJO', 'fecha_vencimiento' => now()->addDays(5),
            'cantidad_inicial' => 4, 'cantidad_disponible' => 4]);

        $almacen = $this->draft();
        $this->postJson("/api/almacenes/{$almacen['id']}/detalles", ['producto_id' => $low->id, 'cantidad' => 9,
            'fecha_vencimiento' => now()->addDays(20)->toDateString()])->assertCreated();
        $this->postJson("/api/almacenes/{$almacen['id']}/detalles", ['producto_id' => $high->id, 'cantidad' => 1])->assertCreated();
        $this->postJson("/api/almacenes/{$almacen['id']}/aplicar")->assertOk();

        // Sobrante: lote nuevo por la diferencia (5), no por el total contado.
        $lot = Lote::where('producto_id', $low->id)->whereNotNull('almacen_detalle_id')->firstOrFail();
        $this->assertSame(5.0, (float) $lot->cantidad_inicial);
        // Faltante: se descuenta del lote existente.
        $this->assertSame(1.0, (float) Lote::where('producto_id', $high->id)->whereNull('almacen_detalle_id')->first()->cantidad_disponible);

        // Anular devuelve todo a como estaba.
        $this->putJson("/api/almacenes/{$almacen['id']}/anular")->assertOk()->assertJsonPath('estado', 'ANULADO');
        $this->assertSame(4.0, (float) $low->fresh()->stock_inicial);
        $this->assertSame(4.0, (float) $high->fresh()->stock_inicial);
        $this->assertSame(4.0, (float) Lote::where('producto_id', $high->id)->whereNull('almacen_detalle_id')->first()->cantidad_disponible);
        $this->assertSame(0, Lote::whereNotNull('almacen_detalle_id')->count());
    }

    public function test_a_product_can_be_counted_in_several_lots(): void
    {
        $this->admin();
        $product = Producto::first();
        $product->update(['stock_inicial' => 6]);
        $old = Lote::create(['producto_id' => $product->id, 'lote' => 'VIEJO', 'fecha_vencimiento' => now()->addDays(3),
            'cantidad_inicial' => 6, 'cantidad_disponible' => 6]);

        $almacen = $this->draft();
        $line = $this->postJson("/api/almacenes/{$almacen['id']}/detalles", [
            'producto_id' => $product->id,
            'conteos' => [
                ['lote' => 'A', 'fecha_vencimiento' => now()->addDays(10)->toDateString(), 'cantidad' => 3],
                ['lote' => 'B', 'fecha_vencimiento' => now()->addDays(40)->toDateString(), 'cantidad' => 5],
            ],
        ])->assertCreated()->assertJsonCount(2, 'conteos')->json();

        // La cantidad del producto es la suma de sus lotes.
        $this->assertSame('8.000', $line['cantidad']);

        // Al reabrir el producto se recupera lo cargado: cantidad, lotes y vencimientos.
        $reopened = collect($this->getJson("/api/almacenes/{$almacen['id']}")->assertOk()->json('detalles'))
            ->firstWhere('producto_id', $product->id);
        $this->assertCount(2, $reopened['conteos']);
        $this->assertSame('A', $reopened['conteos'][0]['lote']);

        // Editar reemplaza los lotes de esa lÃ­nea.
        $this->putJson("/api/almacenes/{$almacen['id']}/detalles/{$line['id']}", [
            'conteos' => [['lote' => 'A', 'fecha_vencimiento' => now()->addDays(10)->toDateString(), 'cantidad' => 4]],
        ])->assertOk()->assertJsonCount(1, 'conteos')->assertJsonPath('cantidad', '4.000');

        $this->postJson("/api/almacenes/{$almacen['id']}/aplicar")->assertOk();

        // Lo contado por lote reemplaza a los lotes del sistema.
        $this->assertSame(4.0, (float) $product->fresh()->stock_inicial);
        $this->assertSame(0.0, (float) $old->fresh()->cantidad_disponible);
        $lots = Lote::where('producto_id', $product->id)->whereNotNull('almacen_detalle_id')->get();
        $this->assertCount(1, $lots);
        $this->assertSame(4.0, (float) $lots->first()->cantidad_disponible);

        // Anular devuelve los lotes originales y borra los de la revisiÃ³n.
        $this->putJson("/api/almacenes/{$almacen['id']}/anular")->assertOk();
        $this->assertSame(6.0, (float) $product->fresh()->stock_inicial);
        $this->assertSame(6.0, (float) $old->fresh()->cantidad_disponible);
        $this->assertSame(0, Lote::whereNotNull('almacen_detalle_id')->count());
    }

    public function test_a_product_counted_twice_warns_instead_of_duplicating(): void
    {
        $admin = $this->admin();
        $product = Producto::first();
        $almacen = $this->draft();

        $this->postJson("/api/almacenes/{$almacen['id']}/detalles", ['producto_id' => $product->id, 'cantidad' => 3])->assertCreated();

        // Otro usuario cuenta el mismo producto: recibe aviso en vez de duplicar la lÃ­nea.
        $other = User::create(['name' => 'MARIA', 'username' => 'maria', 'password' => bcrypt('123456')]);
        $other->givePermissionTo(['Ver Almacenes', 'Editar Almacenes']);
        Sanctum::actingAs($other);
        $this->postJson("/api/almacenes/{$almacen['id']}/detalles", ['producto_id' => $product->id, 'cantidad' => 5])
            ->assertStatus(409);

        // Con "reemplazar" corrige la lÃ­nea y queda a su nombre.
        $this->postJson("/api/almacenes/{$almacen['id']}/detalles", [
            'producto_id' => $product->id, 'cantidad' => 5, 'reemplazar' => true,
        ])->assertCreated();

        $details = Almacen::find($almacen['id'])->detalles;
        $this->assertCount(1, $details);
        $this->assertSame(5.0, (float) $details->first()->cantidad);
        $this->assertSame('MARIA', $details->first()->usuario_nombre);
        $this->assertNotSame($admin->id, $details->first()->user_id);
    }

    public function test_applied_almacen_is_locked_and_permissions_are_required(): void
    {
        $this->admin();
        $product = Producto::first();
        $almacen = $this->draft();
        $this->postJson("/api/almacenes/{$almacen['id']}/detalles", ['producto_id' => $product->id, 'cantidad' => 2])->assertCreated();
        $this->postJson("/api/almacenes/{$almacen['id']}/aplicar")->assertOk();

        $this->postJson("/api/almacenes/{$almacen['id']}/detalles", ['producto_id' => $product->id, 'cantidad' => 1])->assertStatus(422);
        $this->putJson("/api/almacenes/{$almacen['id']}", ['descripcion' => 'OTRA'])->assertStatus(422);

        $user = User::create(['name' => 'DEPOSITO', 'username' => 'deposito', 'password' => bcrypt('123456')]);
        Sanctum::actingAs($user);
        $this->getJson('/api/almacenes')->assertForbidden();

        $user->givePermissionTo(['Ver Almacenes', 'Crear Almacenes', 'Editar Almacenes']);
        $nuevo = $this->draft();
        $this->postJson("/api/almacenes/{$nuevo['id']}/detalles", ['producto_id' => $product->id, 'cantidad' => 1])->assertCreated();
        // Sin "Aplicar Almacenes" no puede tocar el stock.
        $this->postJson("/api/almacenes/{$nuevo['id']}/aplicar")->assertForbidden();
        $this->assertSame('BORRADOR', Almacen::find($nuevo['id'])->estado);
    }

    public function test_lots_from_a_review_show_up_in_expirations_with_their_origin(): void
    {
        $this->admin();
        $product = Producto::first();
        $product->update(['stock_inicial' => 0]);

        $almacen = $this->draft();
        $this->postJson("/api/almacenes/{$almacen['id']}/detalles", [
            'producto_id' => $product->id,
            'conteos' => [['lote' => 'REV-1', 'fecha_vencimiento' => now()->addDays(5)->toDateString(), 'cantidad' => 7]],
        ])->assertCreated();
        $this->postJson("/api/almacenes/{$almacen['id']}/aplicar")->assertOk();

        $row = collect($this->getJson('/api/vencimientos?estado=por_vencer&dias=30')->assertOk()->json())
            ->firstWhere('lote', 'REV-1');

        $this->assertNotNull($row, 'El lote de la revisiÃ³n debe aparecer en Por vencer');
        $this->assertSame('ALMACEN', $row['origen']);
        $this->assertSame($almacen['numero'], $row['documento']);
    }

    public function test_progress_endpoint_reports_review_status(): void
    {
        $this->admin();
        $product = Producto::first();
        $product->update(['stock_inicial' => 6]);
        $almacen = $this->draft();
        $this->postJson("/api/almacenes/{$almacen['id']}/detalles", ['producto_id' => $product->id, 'cantidad' => 4])->assertCreated();

        $this->getJson("/api/almacenes/{$almacen['id']}/avance")
            ->assertOk()
            ->assertJsonPath('revisados', 1)
            ->assertJsonPath('con_diferencia', 1)
            ->assertJsonPath('total_productos', Producto::count())
            ->assertJsonPath('detalles.0.diferencia_actual', -2);
    }
}
