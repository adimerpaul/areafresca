<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('ventas')
            ->where('monto_efectivo', 0)
            ->where('monto_qr', 0)
            ->update([
                'tipo_pago' => 'EFECTIVO',
                'monto_efectivo' => DB::raw('total'),
            ]);
    }

    public function down(): void
    {
        //
    }
};
