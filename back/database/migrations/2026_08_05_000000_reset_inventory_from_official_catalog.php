<?php

use App\Services\ProductCategoryClassifier;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Reinicia ventas, compras e inventario usando el catálogo oficial recibido
 * el 2026-08-05. El CSV se generó desde "precios y stock actual (2).xlsx"
 * (SHA-256 535A5D76EFB9BC32BA74ABD1B050247E0BC1F23A06AA5A4B7FF0BC063626C983).
 */
return new class extends Migration
{
    private const TRANSACTION_TABLES = [
        'baja_detalle_lotes',
        'venta_detalle_lotes',
        'lotes',
        'venta_detalles',
        'ventas',
        'compra_detalles',
        'compras',
        'siat_eventos_significativos',
    ];

    public function up(): void
    {
        if (! Schema::hasColumn('productos', 'precio_4')) {
            Schema::table('productos', function (Blueprint $table) {
                $table->decimal('precio_4', 12, 2)->nullable()->after('precio_3');
            });
        }

        $products = $this->readCatalog();

        DB::transaction(function () use ($products) {
            foreach (self::TRANSACTION_TABLES as $table) {
                DB::table($table)->delete();
            }

            $officialIds = [];
            foreach ($products as $product) {
                $existing = DB::table('productos')
                    ->where('codigo', $product['codigo'])
                    ->orWhere('codigo_barras', $product['codigo'])
                    ->orderByRaw('codigo = ? desc', [$product['codigo']])
                    ->first();

                if ($existing) {
                    DB::table('productos')->where('id', $existing->id)->update($product + [
                        'deleted_at' => null,
                        'updated_at' => now(),
                    ]);
                    $officialIds[] = $existing->id;
                } else {
                    $officialIds[] = DB::table('productos')->insertGetId($product + [
                        'categoria' => null,
                        'categoria_id' => null,
                        'unidad' => $this->unitFor($product['codigo']),
                        'foto' => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            DB::table('productos')->whereNotIn('id', $officialIds)->update([
                'deleted_at' => now(),
                'stock_inicial' => 0,
                'precio_4' => null,
                'updated_at' => now(),
            ]);
        });

        app(ProductCategoryClassifier::class)->classifyAll();
    }

    public function down(): void
    {
        if (Schema::hasColumn('productos', 'precio_4')) {
            Schema::table('productos', fn (Blueprint $table) => $table->dropColumn('precio_4'));
        }
    }

    /** @return list<array<string, mixed>> */
    private function readCatalog(): array
    {
        $path = database_path('data/precios-stock-oficial-2026-08-05.csv');
        if (! is_file($path)) {
            throw new RuntimeException("No se encontró el catálogo oficial en {$path}");
        }

        $handle = fopen($path, 'r');
        $header = fgetcsv($handle);
        $expected = ['codigo', 'nombre', 'fecha_registro', 'stock', 'precio_compra', 'precio_1', 'precio_2', 'precio_3'];
        if ($header !== $expected) {
            fclose($handle);
            throw new RuntimeException('El catálogo oficial no tiene las columnas esperadas.');
        }

        $products = [];
        $seen = [];
        $line = 1;
        while (($row = fgetcsv($handle)) !== false) {
            $line++;
            [$code, $name, $date, $stock, $purchasePrice, $first, $second, $third] = $row;
            $code = trim((string) $code);
            $name = preg_replace('/\s+/u', ' ', trim((string) $name));
            if ($code === '' && $name === '') {
                continue;
            }
            if ($code === '' || $name === '' || isset($seen[$code])) {
                fclose($handle);
                throw new RuntimeException("Línea {$line}: código vacío, nombre vacío o código duplicado.");
            }
            foreach (['stock' => $stock, 'precio_compra' => $purchasePrice, 'precio_1' => $first] as $field => $value) {
                if (! is_numeric($value) || (float) $value < 0) {
                    fclose($handle);
                    throw new RuntimeException("Línea {$line}: {$field} no es un número válido.");
                }
            }
            foreach (['precio_2' => $second, 'precio_3' => $third] as $field => $value) {
                if ($value !== '' && (! is_numeric($value) || (float) $value < 0)) {
                    fclose($handle);
                    throw new RuntimeException("Línea {$line}: {$field} no es un número válido.");
                }
            }
            if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $date)) {
                fclose($handle);
                throw new RuntimeException("Línea {$line}: fecha de registro inválida.");
            }

            $seen[$code] = true;
            $products[] = [
                'codigo' => $code,
                'codigo_barras' => $code,
                'nombre' => mb_strtoupper($name),
                'fecha_registro' => $date,
                'stock_inicial' => round((float) $stock, 3),
                'precio_compra' => round((float) $purchasePrice, 2),
                'precio_venta' => round((float) $first, 2),
                'precio_1' => round((float) $first, 2),
                'precio_2' => $second === '' ? 0 : round((float) $second, 2),
                'precio_3' => $third === '' ? 0 : round((float) $third, 2),
                'precio_4' => null,
            ];
        }
        fclose($handle);

        if (count($products) !== 366) {
            throw new RuntimeException('El catálogo oficial debe contener exactamente 366 productos.');
        }

        return $products;
    }

    private function unitFor(string $code): string
    {
        return strlen($code) <= 7 && (str_starts_with($code, '21') || str_starts_with($code, '22')) ? 'KG' : 'UND';
    }
};
