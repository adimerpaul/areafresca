<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('productos')) {
            return;
        }

        Schema::table('productos', function (Blueprint $table) {
            if (! Schema::hasColumn('productos', 'codigo_barras')) {
                $table->string('codigo_barras', 100)->nullable()->index()->after('codigo');
            }
            if (! Schema::hasColumn('productos', 'categoria')) {
                $table->string('categoria', 100)->nullable()->index()->after('nombre');
            }
            if (! Schema::hasColumn('productos', 'unidad')) {
                $table->string('unidad', 20)->nullable()->after('categoria');
            }
            if (! Schema::hasColumn('productos', 'precio_compra')) {
                $table->decimal('precio_compra', 12, 2)->default(0)->after('unidad');
            }
            if (! Schema::hasColumn('productos', 'precio_venta')) {
                $table->decimal('precio_venta', 12, 2)->default(0)->after('precio_compra');
            }
            if (! Schema::hasColumn('productos', 'stock_inicial')) {
                $table->unsignedInteger('stock_inicial')->default(0)->after('precio_venta');
            }
            if (! Schema::hasColumn('productos', 'foto')) {
                $table->string('foto')->nullable()->after('stock_inicial');
            }
        });
    }

    public function down(): void
    {
        // No se eliminan columnas para preservar inventario y compatibilidad.
    }
};
