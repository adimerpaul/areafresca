<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('compras', function (Blueprint $table) {
            $table->decimal('monto_efectivo', 14, 2)->default(0)->after('tipo_pago');
            $table->decimal('monto_qr', 14, 2)->default(0)->after('monto_efectivo');
        });
    }

    public function down(): void
    {
        Schema::table('compras', function (Blueprint $table) {
            $table->dropColumn(['monto_efectivo', 'monto_qr']);
        });
    }
};
