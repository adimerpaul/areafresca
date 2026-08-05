<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('baja_detalles', function (Blueprint $table) {
            $table->decimal('precio_venta', 12, 4)->nullable()->after('precio_compra');
        });

        DB::table('baja_detalles')->update(['precio_venta' => DB::raw('precio_compra')]);
    }

    public function down(): void
    {
        Schema::table('baja_detalles', fn (Blueprint $table) => $table->dropColumn('precio_venta'));
    }
};
