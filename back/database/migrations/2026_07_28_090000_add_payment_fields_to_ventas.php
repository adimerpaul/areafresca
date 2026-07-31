<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->string('tipo_pago', 20)->default('EFECTIVO')->after('total');
            $table->decimal('monto_efectivo', 12, 2)->default(0)->after('tipo_pago');
            $table->decimal('monto_qr', 12, 2)->default(0)->after('monto_efectivo');
            $table->index('tipo_pago');
        });
    }

    public function down(): void
    {
        Schema::table('ventas', fn (Blueprint $table) => $table->dropColumn(['tipo_pago', 'monto_efectivo', 'monto_qr']));
    }
};
