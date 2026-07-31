<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->decimal('stock_inicial', 12, 3)->default(0)->change();
        });

        Schema::table('venta_detalles', function (Blueprint $table) {
            $table->decimal('cantidad', 12, 3)->change();
        });
    }

    public function down(): void
    {
        // Se conserva la precisión decimal para no perder inventario por peso.
    }
};
