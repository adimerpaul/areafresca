<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Producto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BeanCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_contains_exactly_one_hundred_test_products(): void
    {
        $this->assertSame(100, Producto::count());
        $this->assertSame(10, Categoria::count());
        $this->assertSame(100, Producto::where('codigo', 'like', 'BEAN-%')->count());
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
