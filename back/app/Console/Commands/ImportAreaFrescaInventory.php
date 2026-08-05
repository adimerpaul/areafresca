<?php

namespace App\Console\Commands;

use App\Models\Producto;
use App\Services\ProductCategoryClassifier;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Throwable;

class ImportAreaFrescaInventory extends Command
{
    protected $signature = 'inventory:import {file : Ruta del archivo XLSX} {--replace : Reemplaza el catalogo existente}';

    protected $description = 'Importa el inventario inicial de Area Fresca desde Excel';

    public function handle(ProductCategoryClassifier $classifier): int
    {
        $path = realpath($this->argument('file'));
        if (! $path || ! is_file($path)) {
            $this->error('No se encontro el archivo indicado.');
            return self::FAILURE;
        }

        try {
            $sheet = IOFactory::load($path)->getActiveSheet();
            $headers = array_map(
                fn ($value) => trim((string) $value),
                $sheet->rangeToArray('A1:F1', null, true, true, false)[0]
            );

            if ($headers !== ['Cod. Producto', 'Producto', 'Fecha Reg.', 'Saldo', 'Pre.Compra', 'Pre.Venta']) {
                $this->error('El archivo no tiene las columnas esperadas en A:F.');
                return self::FAILURE;
            }

            $rows = [];
            $seen = [];
            for ($row = 2; $row <= $sheet->getHighestDataRow(); $row++) {
                [$rawCode, $rawName, $rawDate, $rawStock, $rawPurchase, $rawSale] =
                    $sheet->rangeToArray("A{$row}:F{$row}", null, true, true, false)[0];

                $barcode = trim((string) $rawCode);
                $name = preg_replace('/\s+/u', ' ', trim((string) $rawName));
                if ($barcode === '' && $name === '') {
                    continue;
                }
                if ($barcode === '' || $name === '') {
                    throw new \RuntimeException("Fila {$row}: codigo de barras o nombre vacio.");
                }
                if (isset($seen[$barcode])) {
                    throw new \RuntimeException("Fila {$row}: codigo de barras duplicado {$barcode}.");
                }
                $seen[$barcode] = true;

                $stock = round((float) $rawStock, 3);
                $rows[] = [
                    'codigo' => $barcode,
                    'codigo_barras' => $barcode,
                    'nombre' => $name,
                    'categoria' => 'INVENTARIO INICIAL',
                    'unidad' => $this->unitFor($barcode, $stock),
                    'precio_compra' => round((float) $rawPurchase, 2),
                    'precio_venta' => round((float) $rawSale, 2),
                    'stock_inicial' => $stock,
                    'fecha_registro' => $this->parseDate($rawDate, $row),
                    'updated_at' => now(),
                    'created_at' => now(),
                ];
            }

            DB::transaction(function () use ($rows) {
                if ($this->option('replace')) {
                    Producto::query()->forceDelete();
                }
                Producto::query()->upsert(
                    $rows,
                    ['codigo_barras'],
                    ['codigo', 'nombre', 'categoria', 'unidad', 'precio_compra', 'precio_venta', 'stock_inicial', 'fecha_registro', 'updated_at']
                );
            });

            $classifier->classifyAll();

            $this->info(count($rows).' productos importados correctamente.');
            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error('No se pudo importar: '.$exception->getMessage());
            return self::FAILURE;
        }
    }

    private function parseDate(mixed $value, int $row): string
    {
        try {
            if (is_numeric($value)) {
                return Carbon::instance(ExcelDate::excelToDateTimeObject((float) $value))->toDateString();
            }
            return Carbon::createFromFormat('m/d/Y', trim((string) $value))->toDateString();
        } catch (Throwable) {
            throw new \RuntimeException("Fila {$row}: fecha de registro invalida.");
        }
    }

    private function unitFor(string $barcode, float $stock): string
    {
        $isInternalWeightCode = strlen($barcode) <= 7 && (str_starts_with($barcode, '21') || str_starts_with($barcode, '22'));
        return $isInternalWeightCode || abs($stock - round($stock)) > 0.0001 ? 'KG' : 'UND';
    }
}
