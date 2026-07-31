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
        Schema::create('compras', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->unsignedBigInteger('proveedor_id')->nullable();
            $table->foreign('proveedor_id')->references('id')->on('proveedores');
            $table->date('fecha')->nullable();
            $table->time('hora')->nullable();
            $table->string('ci')->nullable();
            $table->string('nombre')->nullable();
            $table->string('estado')->nullable()->default('Activo');
            $table->decimal('total', 8, 2)->nullable();
            $table->string('tipo_pago')->nullable()->default('Efectivo');
            $table->string('nro_factura')->nullable();
            $table->string('agencia')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('compras');
    }
};
