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
        $expected = count(file(database_path('data/precios-oficiales.csv'), FILE_SKIP_EMPTY_LINES)) - 1;

        $this->assertSame($expected, Producto::count());
        $this->assertSame(0, Producto::where('codigo', 'like', 'BEAN-%')->count());
        $this->assertSame(0, Producto::whereNull('categoria_id')->count());
    }

    public function test_official_products_start_without_stock_and_priced_from_the_first_tier(): void
    {
        $this->assertSame(0, Producto::where('stock_inicial', '>', 0)->count());
        $this->assertSame(0, Producto::where('precio_1', '<=', 0)->count());
        $this->assertSame(0, Producto::whereColumn('precio_venta', '!=', 'precio_1')->count());
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
