<?php

namespace App\Console\Commands;

use App\Services\Siat\SiatService;
use Illuminate\Console\Command;
use Throwable;

class RenovarCufd extends Command
{
    protected $signature = 'siat:renovar-cufd';

    protected $description = 'Solicita y guarda un nuevo CUFD para la facturación electrónica';

    public function handle(SiatService $siat): int
    {
        try {
            $cufd = $siat->renewCufd();
            $this->info("CUFD generado correctamente. Vigente hasta: {$cufd->vence_en}");

            return self::SUCCESS;
        } catch (Throwable $exception) {
            report($exception);
            $this->error('No se pudo generar el CUFD: '.$exception->getMessage());

            return self::FAILURE;
        }
    }
}
