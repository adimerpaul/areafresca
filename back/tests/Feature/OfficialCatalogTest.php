<?php

namespace Tests\Feature;

use App\Models\Compra;
use App\Models\Producto;
use App\Models\Venta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OfficialCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_matches_the_official_price_list(): void
    {
        $expected = count(file(database_path('data/precios-stock-oficial-2026-08-05.csv'), FILE_SKIP_EMPTY_LINES)) - 1;

        $this->assertSame($expected, Producto::count());
        $this->assertSame(366, Producto::count());
        $this->assertSame(0, Producto::where('codigo', 'like', 'BEAN-%')->count());
        $this->assertSame(0, Producto::whereNull('categoria_id')->count());
    }

    public function test_official_products_have_current_stock_and_prices_from_the_spreadsheet(): void
    {
        $product = Producto::where('codigo', '7792070000938')->firstOrFail();

        $this->assertSame(4.0, (float) $product->stock_inicial);
        $this->assertSame(25.83, (float) $product->precio_compra);
        $this->assertSame(34.10, (float) $product->precio_1);
        $this->assertSame(33.30, (float) $product->precio_2);
        $this->assertSame(32.60, (float) $product->precio_3);
        $this->assertNull($product->precio_4);
        $this->assertSame(0, Producto::whereColumn('precio_venta', '!=', 'precio_1')->count());
        $this->assertSame(0, Producto::whereNotNull('precio_4')->count());
        $this->assertNotNull(Producto::where('codigo', '11194')->first());
    }

    public function test_sales_and_purchases_start_empty(): void
    {
        $this->assertSame(0, Venta::withTrashed()->count());
        $this->assertSame(0, Compra::withTrashed()->count());
    }

    public function test_admin_can_login_and_receive_a_sanctum_token(): void
    {
        $this->postJson('/api/login', [
            'username' => 'admin',
            'password' => 'admin',
        ])->assertOk()
            ->assertJsonStructure(['token', 'user', 'must_change_password'])
            ->assertJsonPath('user.username', 'admin');
    }
}
