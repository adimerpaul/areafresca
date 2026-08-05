<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Actualiza solo el stock (stock_inicial) de los productos del catálogo
 * oficial con los saldos reales del inventario físico, cargados desde
 * database/data/stock-inicial.csv (exportado del Excel de saldos).
 * No crea ni borra productos: solo actualiza los que ya existen por código.
 */
return new class extends Migration
{
    public function up(): void
    {
        $stocks = $this->readStocks();

        DB::transaction(function () use ($stocks) {
            foreach ($stocks as $code => $stock) {
                DB::table('productos')
                    ->whereNull('deleted_at')
                    ->where(function ($query) use ($code) {
                        $query->where('codigo', $code)
                            ->orWhere('codigo_barras', $code);
                    })
                    ->update(['stock_inicial' => $stock, 'updated_at' => now()]);
            }
        });
    }

    public function down(): void
    {
        // No reversible: no se conserva el stock anterior a esta actualización.
    }

    /** @return array<string, float> codigo => stock */
    private function readStocks(): array
    {
        $path = database_path('data/stock-inicial.csv');
        if (! is_file($path)) {
            throw new RuntimeException("No se encontró el archivo de saldos en {$path}");
        }

        $handle = fopen($path, 'r');
        $header = fgetcsv($handle);
        $header[0] = preg_replace('/^\x{FEFF}/u', '', (string) $header[0]);
        if ($header !== ['codigo', 'stock']) {
            fclose($handle);
            throw new RuntimeException('El archivo de saldos no tiene las columnas esperadas.');
        }

        $stocks = [];
        $line = 1;
        while (($row = fgetcsv($handle)) !== false) {
            $line++;
            [$code, $stock] = $row;
            $code = trim((string) $code);
            if ($code === '') {
                continue;
            }
            if (isset($stocks[$code])) {
                fclose($handle);
                throw new RuntimeException("Línea {$line}: código duplicado {$code} en el archivo de saldos.");
            }
            $stocks[$code] = round((float) $stock, 3);
        }
        fclose($handle);

        if ($stocks === []) {
            throw new RuntimeException('El archivo de saldos está vacío.');
        }

        return $stocks;
    }
};
