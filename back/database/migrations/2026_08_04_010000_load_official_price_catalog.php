<?php

use App\Services\ProductCategoryClassifier;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Deja el sistema con el inventario oficial de Area Fresca:
 * borra ventas, compras y el catálogo de prueba, y carga la lista
 * de precios vigente desde database/data/precios-oficiales.csv.
 */
return new class extends Migration
{
    /** Tablas transaccionales, de hijas a padres para no romper las llaves foráneas. */
    private const TRANSACTION_TABLES = [
        'venta_detalle_lotes', 'venta_detalles', 'ventas',
        'lotes', 'compra_detalles', 'compras',
    ];

    private const CATALOG_DATE = '2026-08-01';

    public function up(): void
    {
        $products = $this->readCatalog();

        DB::transaction(function () use ($products) {
            foreach (self::TRANSACTION_TABLES as $table) {
                DB::table($table)->delete();
            }
            DB::table('productos')->delete();
            DB::table('categorias')->delete();

            foreach (array_chunk($products, 200) as $chunk) {
                DB::table('productos')->insert($chunk);
            }
        });

        app(ProductCategoryClassifier::class)->classifyAll();
    }

    public function down(): void
    {
        DB::table('productos')->delete();
    }

    /** @return list<array<string, mixed>> */
    private function readCatalog(): array
    {
        $path = database_path('data/precios-oficiales.csv');
        if (! is_file($path)) {
            throw new RuntimeException("No se encontró el catálogo oficial en {$path}");
        }

        $handle = fopen($path, 'r');
        $header = fgetcsv($handle);
        $header[0] = preg_replace('/^\x{FEFF}/u', '', (string) $header[0]);
        if ($header !== ['codigo', 'nombre', 'precio_1', 'precio_2', 'precio_3']) {
            fclose($handle);
            throw new RuntimeException('El catálogo oficial no tiene las columnas esperadas.');
        }

        $products = [];
        $seen = [];
        $line = 1;
        while (($row = fgetcsv($handle)) !== false) {
            $line++;
            [$code, $name, $first, $second, $third] = $row;
            $code = trim((string) $code);
            $name = preg_replace('/\s+/u', ' ', trim((string) $name));
            if ($code === '' && $name === '') {
                continue;
            }
            if ($code === '' || $name === '') {
                fclose($handle);
                throw new RuntimeException("Línea {$line}: código o nombre vacío en el catálogo oficial.");
            }
            if (isset($seen[$code])) {
                fclose($handle);
                throw new RuntimeException("Línea {$line}: código duplicado {$code} en el catálogo oficial.");
            }
            $seen[$code] = true;

            $products[] = [
                'codigo' => $code,
                'codigo_barras' => $code,
                'nombre' => mb_strtoupper($name),
                'categoria' => null,
                'categoria_id' => null,
                'unidad' => $this->unitFor($code),
                'precio_compra' => 0,
                // precio_venta es el precio activo del punto de venta; arranca en la escala 1.
                'precio_venta' => round((float) $first, 2),
                'precio_1' => round((float) $first, 2),
                'precio_2' => round((float) $second, 2),
                'precio_3' => round((float) $third, 2),
                'stock_inicial' => 0,
                'fecha_registro' => self::CATALOG_DATE,
                'foto' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        fclose($handle);

        if ($products === []) {
            throw new RuntimeException('El catálogo oficial está vacío.');
        }

        return $products;
    }

    /** Los códigos internos cortos que empiezan en 21/22 son productos pesados en balanza. */
    private function unitFor(string $code): string
    {
        $isWeighted = strlen($code) <= 7
            && (str_starts_with($code, '21') || str_starts_with($code, '22'));

        return $isWeighted ? 'KG' : 'UND';
    }
};
