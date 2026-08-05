<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->date('fecha_registro')->nullable()->after('stock_inicial');
        });
    }

    public function down(): void
    {
        Schema::table('productos', fn (Blueprint $table) => $table->dropColumn('fecha_registro'));
    }
};
