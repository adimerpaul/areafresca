<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * El listado de facturación cruza cada CUF del libro del SIAT contra `ventas`
 * para saber qué facturas nunca se registraron en el sistema. `ventas.cuf` es
 * TEXT, así que en MySQL el índice va sobre un prefijo (el CUF mide 57).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE ventas ADD INDEX ventas_cuf_index (cuf(64))');

            return;
        }

        Schema::table('ventas', fn (Blueprint $table) => $table->index('cuf', 'ventas_cuf_index'));
    }

    public function down(): void
    {
        Schema::table('ventas', fn (Blueprint $table) => $table->dropIndex('ventas_cuf_index'));
    }
};
