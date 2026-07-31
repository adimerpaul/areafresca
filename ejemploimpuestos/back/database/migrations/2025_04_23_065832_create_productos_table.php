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
        Schema::create('productos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre')->nullable();
            $table->string('barra')->nullable();
            $table->integer('stockAlmacen')->nullable()->default(0)->comment('cantidad en stock almacen');
            $table->integer('stockChallgua')->nullable()->default(0)->comment('cantidad en stock sucursal challgua');
            $table->integer('stockSocavon')->nullable()->default(0)->comment('cantidad en stock sucursal socavon');
            $table->integer('stockCatalina')->nullable()->default(0)->comment('cantidad en stock sucursal catalina');
//            $table->integer('cantidadSucursal4')->nullable()->default(0)->comment('cantidad en stock sucursal 4');
            $table->double('costo',10,2)->nullable();
            $table->double('precioAntes',10,2)->nullable();
            $table->double('precio',10,2)->nullable();
            $table->double('porcentaje',10,2)->nullable();
            //$table->double('utilidad',10,2)->nullable();
            $table->string('activo')->default('ACTIVO');
            $table->string('unidad')->default('UNIDAD');
            $table->string('registroSanitario')->nullable();
            $table->string('paisOrigen')->nullable();
            $table->string('nombreComun')->nullable();
            $table->string('composicion')->nullable();
            $table->string('marca')->nullable();
            $table->string('distribuidora')->nullable();
            $table->string('imagen')->nullable()->default('productDefault.jpg');
//            $table->string('color')->nullable();
            $table->text('descripcion')->nullable();
            $table->string('categoria')->nullable();
            $table->string('subcategoria')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('productos');
    }
};
