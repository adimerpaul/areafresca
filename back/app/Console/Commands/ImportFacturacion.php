<?php

namespace App\Console\Commands;

use App\Services\FacturacionImporter;
use Illuminate\Console\Command;
use Throwable;

class ImportFacturacion extends Command
{
    protected $signature = 'facturacion:import {file : Ruta del XLSX del libro de ventas del SIAT}';

    protected $description = 'Importa el libro de ventas del SIAT; omite las facturas cuyo CUF ya existe';

    public function handle(FacturacionImporter $importer): int
    {
        $path = realpath($this->argument('file'));
        if (! $path || ! is_file($path)) {
            $this->error('No se encontro el archivo indicado.');

            return self::FAILURE;
        }

        try {
            $result = $importer->import($path, basename($path));
        } catch (Throwable $exception) {
            $this->error('No se pudo importar: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->info("Filas leidas: {$result['total']}");
        $this->info("Insertadas: {$result['insertados']}");
        $this->info("Omitidas por CUF repetido: {$result['duplicados']}");
        $this->info('Meses: '.implode(', ', $result['meses']));

        return self::SUCCESS;
    }
}
