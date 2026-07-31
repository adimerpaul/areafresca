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
        Schema::create('venta_detalles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venta_id')->constrained('ventas');
            $table->foreignId('producto_id')->constrained('productos');
            $table->unsignedBigInteger('compra_detalle_id')->nullable();
            $table->foreign('compra_detalle_id')->references('id')->on('compra_detalles');
            $table->string('nombre')->nullable();
            $table->integer('cantidad')->nullable();
            $table->string('unidad')->nullable();
            $table->string('lote')->nullable();
            $table->date('fecha_vencimiento')->nullable();
            $table->integer('precio')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('venta_detalles');
    }
};
