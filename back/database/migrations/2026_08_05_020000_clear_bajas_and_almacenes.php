<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Limpia el historial de bajas y revisiones de almacén sin modificar
 * el saldo vigente de los productos.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            DB::table('baja_detalle_lotes')->delete();
            DB::table('baja_detalles')->delete();
            DB::table('bajas')->delete();

            DB::table('almacen_detalle_lotes')->delete();
            DB::table('almacen_detalle_conteos')->delete();
            DB::table('lotes')->whereNotNull('almacen_detalle_id')->delete();
            DB::table('almacen_detalles')->delete();
            DB::table('almacenes')->delete();
        });
    }

    public function down(): void
    {
        // No reversible: los registros históricos eliminados no se pueden reconstruir.
    }
};
