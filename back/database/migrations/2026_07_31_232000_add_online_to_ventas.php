<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->boolean('online')->default(false)->index()->after('estado_siat');
        });
        DB::table('ventas')->whereIn('estado_siat', ['VALIDADA', 'ANULADA'])->update(['online' => true]);
    }

    public function down(): void
    {
        Schema::table('ventas', fn (Blueprint $table) => $table->dropColumn('online'));
    }
};
