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
        Schema::create('ventas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->unsignedBigInteger('cliente_id')->nullable();
            $table->foreign('cliente_id')->references('id')->on('clientes');
            $table->date('fecha')->nullable();
            $table->time('hora')->nullable();
//            $table->string('tipo_venta')->nullable()->default('Interno');
            $table->string('ci')->nullable();
            $table->string('nombre')->nullable();
            $table->string('estado')->nullable()->default('Activo');
            $table->string('tipo_comprobante')->nullable()->default('Venta');
            $table->decimal('total', 8, 2)->nullable();
            $table->string('tipo_pago')->nullable()->default('Efectivo');
            $table->string('agencia')->nullable()->default('Challgua');
            $table->string('cuf')->nullable();
            $table->string('cufd')->nullable();
            $table->string('leyenda')->nullable();
            $table->boolean('online')->nullable()->default(false);
//            $table->boolean('pagado_interno')->nullable()->default(false);
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ventas');
    }
};
