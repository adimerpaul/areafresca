<?php

namespace Tests\Feature;

use App\Models\SiatCufd;
use App\Services\Siat\SiatService;
use Carbon\Carbon;
use Mockery\MockInterface;
use Tests\TestCase;

class RenovarCufdCommandTest extends TestCase
{
    public function test_command_renews_the_cufd(): void
    {
        $cufd = new SiatCufd;
        $cufd->vence_en = Carbon::parse('2026-08-05 01:00:00');

        $this->mock(SiatService::class, function (MockInterface $mock) use ($cufd) {
            $mock->shouldReceive('renewCufd')->once()->andReturn($cufd);
        });

        $this->artisan('siat:renovar-cufd')
            ->expectsOutputToContain('CUFD generado correctamente')
            ->assertSuccessful();
    }
}
