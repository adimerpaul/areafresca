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
        Schema::create('evento_significativos', function (Blueprint $table) {
            $table->id();
            $table->string('codigoPuntoVenta')->nullable();
            $table->string('codigoSucursal')->nullable();
            $table->dateTime('fechaHoraFinEvento')->nullable();
            $table->dateTime('fechaHoraInicioEvento')->nullable();
            $table->string('codigoMotivoEvento')->nullable();
            $table->string('descripcion')->nullable();
            $table->string('cufd')->nullable();
            $table->string('cufdEvento')->nullable();
            $table->string('codigoRecepcion')->nullable();
            $table->string('codigoRecepcionEventoSignificativo')->nullable();
            $table->unsignedBigInteger("cufd_id")->nullable();
            $table->foreign("cufd_id")->references("id")->on("cufds");
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('evento_significativos');
    }
};
