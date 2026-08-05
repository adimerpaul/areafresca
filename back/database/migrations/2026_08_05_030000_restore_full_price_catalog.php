<?php

use App\Services\ProductCategoryClassifier;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Devuelve al catálogo los 664 productos de la lista de precios oficial.
 *
 * La migración 2026_08_05_000000 dejó activos sólo los 366 productos con saldo
 * del inventario del 05-08 y dio de baja al resto. Aquí se los vuelve a
 * habilitar con saldo cero, para que se puedan vender y comprar de nuevo.
 *
 * Los productos que ya están activos NO se tocan: sus precios vienen del
 * inventario del 05-08, que es posterior a esta lista.
 */
return new class extends Migration
{
    private const CATALOG_DATE = '2026-08-01';

    public function up(): void
    {
        $catalog = $this->readCatalog();

        DB::transaction(function () use ($catalog) {
            foreach ($catalog as $product) {
                $existing = DB::table('productos')
                    ->where('codigo', $product['codigo'])
                    ->orWhere('codigo_barras', $product['codigo'])
                    ->orderByRaw('codigo = ? desc', [$product['codigo']])
                    ->first();

                if ($existing && $existing->deleted_at === null) {
                    continue;
                }

                if ($existing) {
                    DB::table('productos')->where('id', $existing->id)->update([
                        'nombre' => $product['nombre'],
                        'precio_venta' => $product['precio_1'],
                        'precio_1' => $product['precio_1'],
                        'precio_2' => $product['precio_2'],
                        'precio_3' => $product['precio_3'],
                        'precio_4' => null,
                        'stock_inicial' => 0,
                        'deleted_at' => null,
                        'updated_at' => now(),
                    ]);

                    continue;
                }

                DB::table('productos')->insert($product + [
                    'codigo_barras' => $product['codigo'],
                    'precio_venta' => $product['precio_1'],
                    'precio_compra' => 0,
                    'precio_4' => null,
                    'stock_inicial' => 0,
                    'fecha_registro' => self::CATALOG_DATE,
                    'categoria' => null,
                    'categoria_id' => null,
                    'unidad' => $this->unitFor($product['codigo']),
                    'foto' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });

        app(ProductCategoryClassifier::class)->classifyAll();
    }

    /**
     * Vuelve a dar de baja lo que no tenga saldo ni movimientos, que es como
     * estaba antes. Lo que ya se vendió o compró se queda activo.
     */
    public function down(): void
    {
        $moved = DB::table('venta_detalles')->distinct()->pluck('producto_id')
            ->merge(DB::table('compra_detalles')->distinct()->pluck('producto_id'))
            ->merge(DB::table('lotes')->distinct()->pluck('producto_id'))
            ->filter()->unique()->all();

        DB::table('productos')
            ->whereNull('deleted_at')
            ->where('stock_inicial', '<=', 0)
            ->whereNotIn('id', $moved ?: [0])
            ->update(['deleted_at' => now(), 'updated_at' => now()]);
    }

    /** @return list<array<string, mixed>> */
    private function readCatalog(): array
    {
        $path = database_path('data/precios-oficiales.csv');
        if (! is_file($path)) {
            throw new RuntimeException("No se encontró la lista de precios en {$path}");
        }

        $handle = fopen($path, 'r');
        $header = array_map(
            fn ($column) => trim(preg_replace('/^\x{FEFF}/u', '', (string) $column)),
            fgetcsv($handle) ?: [],
        );
        $expected = ['codigo', 'nombre', 'precio_1', 'precio_2', 'precio_3'];
        if ($header !== $expected) {
            fclose($handle);
            throw new RuntimeException('La lista de precios no tiene las columnas esperadas.');
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
            if ($code === '' || $name === '' || isset($seen[$code])) {
                fclose($handle);
                throw new RuntimeException("Línea {$line}: código vacío, nombre vacío o código duplicado.");
            }
            foreach (['precio_1' => $first, 'precio_2' => $second, 'precio_3' => $third] as $field => $value) {
                if (! is_numeric($value) || (float) $value < 0) {
                    fclose($handle);
                    throw new RuntimeException("Línea {$line}: {$field} no es un número válido.");
                }
            }

            $seen[$code] = true;
            $products[] = [
                'codigo' => $code,
                'nombre' => mb_strtoupper($name),
                'precio_1' => round((float) $first, 2),
                'precio_2' => round((float) $second, 2),
                'precio_3' => round((float) $third, 2),
            ];
        }
        fclose($handle);

        if (count($products) !== 664) {
            throw new RuntimeException('La lista de precios oficial debe contener exactamente 664 productos.');
        }

        return $products;
    }

    private function unitFor(string $code): string
    {
        return strlen($code) <= 7 && (str_starts_with($code, '21') || str_starts_with($code, '22')) ? 'KG' : 'UND';
    }
};
