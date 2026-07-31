<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('compra_detalles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('compra_id')->constrained('compras');
            $table->foreignId('user_id')->constrained('users');
            $table->unsignedBigInteger('proveedor_id')->nullable();
            $table->foreign('proveedor_id')->references('id')->on('proveedores');
            $table->unsignedBigInteger('producto_id')->nullable();
            $table->foreign('producto_id')->references('id')->on('productos');
            $table->string('nombre')->nullable();
            $table->decimal('precio', 8, 2)->nullable();
            $table->decimal('cantidad', 8, 2)->nullable();
            $table->decimal('cantidad_venta', 8, 2)->nullable();
            $table->decimal('total', 8, 2)->nullable();
            $table->decimal('factor', 8, 2)->nullable();

            $table->decimal('precio13', 8, 2)->nullable();
            $table->decimal('total13', 8, 2)->nullable();
            $table->decimal('precio_venta', 8, 2)->nullable();

            $table->string('estado')->nullable()->default('Activo');
            $table->string('lote')->nullable();
            $table->date('fecha_vencimiento')->nullable();
            $table->string('nro_factura')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('compra_detalles');
    }
};
