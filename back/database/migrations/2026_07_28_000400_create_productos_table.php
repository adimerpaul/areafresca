<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('productos', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 50);
            $table->string('codigo_barras', 100)->nullable();
            $table->string('nombre');
            $table->string('categoria', 100)->nullable();
            $table->string('unidad', 20);
            $table->decimal('precio_compra', 12, 2)->default(0);
            $table->decimal('precio_venta', 12, 2)->default(0);
            $table->unsignedInteger('stock_inicial')->default(0);
            $table->string('foto')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['categoria', 'nombre']);
            $table->index('codigo');
            $table->index('codigo_barras');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('productos');
    }
};
