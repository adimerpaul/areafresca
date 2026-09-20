<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Deja rastro de las facturas que no se emitieron en el momento de la venta.
 *
 * La fecha de la venta (`fecha`) no se mueve nunca: sigue siendo el día en que
 * se cobró. La fecha con la que se emitió en Impuestos ya vive en
 * `fecha_emision_siat` y es la que viaja en el XML. Estos dos campos sólo
 * registran quién apretó el botón y cuándo, para poder auditar por qué una
 * venta del día 3 terminó facturada el día 19.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->timestamp('reemitida_en')->nullable()->after('fecha_emision_siat');
            $table->string('reemitida_por', 100)->nullable()->after('reemitida_en');
        });
    }

    public function down(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->dropColumn(['reemitida_en', 'reemitida_por']);
        });
    }
};
