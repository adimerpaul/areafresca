<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * El almacÃ©n pasa a ser una revisiÃ³n fÃ­sica del stock de la tienda: lo contado
     * se vuelve el stock oficial. Por eso cada lÃ­nea guarda el valor antiguo y el
     * nuevo del producto, y un producto sÃ³lo puede aparecer una vez por almacÃ©n
     * (varias personas cargan productos distintos en el mismo documento).
     */
    public function up(): void
    {
        Schema::table('almacen_detalles', function (Blueprint $table) {
            $table->decimal('stock_sistema', 12, 3)->default(0)->after('unidad');
            $table->decimal('stock_anterior', 12, 3)->nullable()->after('cantidad');
            $table->decimal('stock_nuevo', 12, 3)->nullable()->after('stock_anterior');
            $table->decimal('diferencia', 12, 3)->nullable()->after('stock_nuevo');
        });

        // Consolida los duplicados que pudieran existir antes de exigir un producto por almacÃ©n.
        $duplicates = DB::table('almacen_detalles')
            ->select('almacen_id', 'producto_id')
            ->groupBy('almacen_id', 'producto_id')
            ->havingRaw('COUNT(*) > 1')->get();

        foreach ($duplicates as $duplicate) {
            $rows = DB::table('almacen_detalles')
                ->where('almacen_id', $duplicate->almacen_id)
                ->where('producto_id', $duplicate->producto_id)
                ->orderBy('id')->get();
            $first = $rows->shift();
            DB::table('almacen_detalles')->where('id', $first->id)
                ->update(['cantidad' => $rows->sum('cantidad') + $first->cantidad]);
            DB::table('almacen_detalles')->whereIn('id', $rows->pluck('id'))->delete();
        }

        Schema::table('almacen_detalles', function (Blueprint $table) {
            $table->unique(['almacen_id', 'producto_id']);
        });

        // Lotes consumidos cuando el conteo es menor al sistema, para poder reponerlos al anular.
        Schema::create('almacen_detalle_lotes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('almacen_detalle_id')->constrained('almacen_detalles')->cascadeOnDelete();
            $table->foreignId('lote_id')->constrained('lotes')->cascadeOnDelete();
            $table->decimal('cantidad', 12, 3);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('almacen_detalle_lotes');

        Schema::table('almacen_detalles', function (Blueprint $table) {
            $table->dropUnique(['almacen_id', 'producto_id']);
            $table->dropColumn(['stock_sistema', 'stock_anterior', 'stock_nuevo', 'diferencia']);
        });
    }
};
