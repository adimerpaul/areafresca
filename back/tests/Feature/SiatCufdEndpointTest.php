<?php

namespace Tests\Feature;

use App\Models\SiatCufd;
use App\Models\User;
use App\Services\Siat\SiatService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Mockery\MockInterface;
use Tests\TestCase;

class SiatCufdEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_requires_confirmation_when_a_valid_cufd_already_exists(): void
    {
        Sanctum::actingAs(User::where('username', 'admin')->firstOrFail());
        $credentials = ['cuis' => ['id' => 1], 'cufd' => ['id' => 2, 'vence_en' => now()->endOfDay()]];

        $this->mock(SiatService::class, function (MockInterface $mock) use ($credentials) {
            $mock->shouldReceive('localCredentialsStatus')->once()->andReturn($credentials);
            $mock->shouldNotReceive('createCufd');
        });

        $this->postJson('/api/siat-cufd')
            ->assertStatus(409)
            ->assertJsonPath('confirmation_required', true);
    }

    public function test_regenerates_and_returns_the_new_cufd_after_confirmation(): void
    {
        Sanctum::actingAs(User::where('username', 'admin')->firstOrFail());
        $current = ['cuis' => ['id' => 1], 'cufd' => ['id' => 2, 'vence_en' => now()->endOfDay()]];
        $renewed = ['cuis' => ['id' => 1], 'cufd' => ['id' => 3, 'vence_en' => now()->addDay()->endOfDay()]];

        $this->mock(SiatService::class, function (MockInterface $mock) use ($current, $renewed) {
            $mock->shouldReceive('localCredentialsStatus')->twice()->andReturn($current, $renewed);
            $mock->shouldReceive('createCufd')->once()->with(true)->andReturn(new SiatCufd);
        });

        $this->postJson('/api/siat-cufd', ['forzar' => true])
            ->assertCreated()
            ->assertJsonPath('credentials.cufd.id', 3)
            ->assertJsonPath('message', 'CUFD regenerado y guardado correctamente');
    }
}
