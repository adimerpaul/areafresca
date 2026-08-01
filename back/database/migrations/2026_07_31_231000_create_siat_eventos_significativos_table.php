<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('siat_eventos_significativos', function (Blueprint $table) {
            $table->text('cufd');
            $table->text('cufd_evento');
            $table->string('codigo_evento')->nullable();
            $table->text('mensaje')->nullable();
            $table->json('venta_ids')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('siat_eventos_significativos', fn (Blueprint $table) => $table->dropColumn(['cufd', 'cufd_evento', 'codigo_evento', 'mensaje', 'venta_ids']));
    }
};
