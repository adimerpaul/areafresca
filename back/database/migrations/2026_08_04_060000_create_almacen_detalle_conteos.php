<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Un producto puede contarse en varios lotes: cada uno con su fecha de
     * vencimiento y su cantidad. `almacen_detalles.cantidad` pasa a ser la suma
     * de estas lÃ­neas cuando existen.
     */
    public function up(): void
    {
        Schema::create('almacen_detalle_conteos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('almacen_detalle_id')->constrained('almacen_detalles')->cascadeOnDelete();
            $table->string('lote')->nullable();
            $table->date('fecha_vencimiento')->nullable();
            $table->decimal('cantidad', 12, 3);
            $table->timestamps();
        });

        // Lo que ya se cargÃ³ con un solo lote pasa a ser su primera lÃ­nea de conteo.
        $details = DB::table('almacen_detalles')
            ->where(fn ($query) => $query->whereNotNull('lote')->orWhereNotNull('fecha_vencimiento'))
            ->get(['id', 'lote', 'fecha_vencimiento', 'cantidad']);

        foreach ($details as $detail) {
            DB::table('almacen_detalle_conteos')->insert([
                'almacen_detalle_id' => $detail->id,
                'lote' => $detail->lote,
                'fecha_vencimiento' => $detail->fecha_vencimiento,
                'cantidad' => $detail->cantidad,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('almacen_detalle_conteos');
    }
};
