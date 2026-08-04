<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Escalas de precio del catálogo oficial: precio_1 es el de venta al público. */
    private const TIERS = ['precio_1', 'precio_2', 'precio_3'];

    public function up(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $previous = 'precio_venta';
            foreach (self::TIERS as $column) {
                if (! Schema::hasColumn('productos', $column)) {
                    $table->decimal($column, 12, 2)->default(0)->after($previous);
                }
                $previous = $column;
            }
        });
    }

    public function down(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->dropColumn(array_filter(
                self::TIERS,
                fn (string $column) => Schema::hasColumn('productos', $column),
            ));
        });
    }
};
