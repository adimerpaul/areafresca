<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('categorias')) {
            Schema::create('categorias', function (Blueprint $table) {
                $table->id();
                $table->string('nombre');
                $table->string('color', 30)->default('primary');
                $table->timestamps();
                $table->softDeletes();
                $table->index('nombre');
            });
        }

        if (! Schema::hasColumn('productos', 'categoria_id')) {
            Schema::table('productos', function (Blueprint $table) {
                $table->foreignId('categoria_id')->nullable()->after('categoria')
                    ->constrained('categorias')->nullOnDelete();
            });
        }

        $categories = [
            'LECHES' => 'blue', 'YOGURES Y KÉFIR' => 'purple', 'MANTEQUILLAS Y CREMAS' => 'amber',
            'JUGOS Y NÉCTARES' => 'orange', 'AGUAS Y GASEOSAS' => 'light-blue',
            'GALLETAS Y CEREALES' => 'brown', 'SALSAS Y ADEREZOS' => 'red',
            'POSTRES Y DULCES' => 'pink', 'OTROS' => 'blue-grey',
        ];
        $ids = [];
        foreach ($categories as $name => $color) {
            $ids[$name] = DB::table('categorias')->insertGetId([
                'nombre' => $name, 'color' => $color, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        DB::table('productos')->orderBy('id')->each(function ($product) use ($ids) {
            $name = mb_strtoupper($product->nombre);
            $category = match (true) {
                str_contains($name, 'YOGUR'), str_contains($name, 'BIOGURT'), str_contains($name, 'KEFIR') => 'YOGURES Y KÉFIR',
                str_contains($name, 'MANTEQUILLA'), str_contains($name, 'MATEQUILLA'), str_contains($name, 'CREMA DE LECHE') => 'MANTEQUILLAS Y CREMAS',
                str_contains($name, 'JUGO'), str_contains($name, 'PILFRUT'), str_contains($name, 'NÉCTAR'), str_contains($name, 'NECTAR'), str_contains($name, 'JUGUITO'), str_contains($name, 'NARANJITO') => 'JUGOS Y NÉCTARES',
                str_contains($name, 'AGUA'), str_contains($name, 'PEPSI'), str_contains($name, 'COCA QUINA'), str_contains($name, 'CASCADA'), str_contains($name, 'VISCACHANI'), str_contains($name, 'SALVIETI'), str_contains($name, 'MALTA') => 'AGUAS Y GASEOSAS',
                str_contains($name, 'GALLETA'), str_contains($name, 'AVENA'), str_contains($name, 'CORN FLAKES'), str_contains($name, 'CHICOLIKE'), str_contains($name, 'CHOCOLEO'), str_contains($name, 'RIQUITOS') => 'GALLETAS Y CEREALES',
                str_contains($name, 'KETCHUP'), str_contains($name, 'MAYONESA'), str_contains($name, 'MOSTAZA'), str_contains($name, 'SALSA'), str_contains($name, 'SOPA') => 'SALSAS Y ADEREZOS',
                str_contains($name, 'GELATINA'), str_contains($name, 'DULCE'), str_contains($name, 'CONDENSADA') => 'POSTRES Y DULCES',
                str_contains($name, 'LECHE'), str_contains($name, 'CHICOLAC'), str_contains($name, 'CHIQUICHOK'), str_contains($name, 'VAQUITA'), str_contains($name, 'SOYA') => 'LECHES',
                default => 'OTROS',
            };
            DB::table('productos')->where('id', $product->id)->update([
                'categoria_id' => $ids[$category], 'categoria' => $category,
            ]);
        });
    }

    public function down(): void
    {
        // Se preserva la clasificación para no perder información.
    }
};
