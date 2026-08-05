<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->string('tipo_documento', 10)->default('CI')->after('numero');
            $table->string('numero_documento', 30)->default('0')->after('tipo_documento');
            $table->string('complemento', 10)->nullable()->after('numero_documento');
            $table->string('cliente_nombre')->nullable()->after('complemento');
            $table->string('cliente_email')->nullable()->after('cliente_nombre');
            $table->string('tipo_comprobante', 20)->default('RECIBO')->after('cliente_email');
            $table->string('estado_siat', 30)->nullable()->index()->after('tipo_comprobante');
            $table->text('cuf')->nullable()->after('estado_siat');
            $table->text('cufd')->nullable()->after('cuf');
            $table->string('codigo_recepcion')->nullable()->after('cufd');
            $table->string('xml_path')->nullable()->after('codigo_recepcion');
            $table->text('siat_mensaje')->nullable()->after('xml_path');
            $table->dateTime('fecha_emision_siat')->nullable()->after('siat_mensaje');
        });
        Schema::create('siat_cuis', function (Blueprint $table) {
            $table->id(); $table->string('codigo'); $table->dateTime('vence_en');
            $table->unsignedSmallInteger('sucursal')->default(0); $table->unsignedSmallInteger('punto_venta')->default(0);
            $table->timestamps(); $table->softDeletes();
        });
        Schema::create('siat_cufds', function (Blueprint $table) {
            $table->id(); $table->text('codigo'); $table->string('codigo_control'); $table->string('direccion')->nullable();
            $table->dateTime('vence_en'); $table->unsignedSmallInteger('sucursal')->default(0); $table->unsignedSmallInteger('punto_venta')->default(0);
            $table->timestamps(); $table->softDeletes();
        });
        Schema::create('siat_eventos_significativos', function (Blueprint $table) {
            $table->id(); $table->unsignedSmallInteger('codigo_motivo'); $table->text('descripcion')->nullable();
            $table->dateTime('inicio'); $table->dateTime('fin')->nullable(); $table->string('estado', 30)->default('ABIERTO');
            $table->string('codigo_recepcion')->nullable(); $table->timestamps(); $table->softDeletes();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('siat_eventos_significativos'); Schema::dropIfExists('siat_cufds'); Schema::dropIfExists('siat_cuis');
        Schema::table('ventas', fn (Blueprint $table) => $table->dropColumn(['tipo_documento','numero_documento','complemento','cliente_nombre','cliente_email','tipo_comprobante','estado_siat','cuf','cufd','codigo_recepcion','xml_path','siat_mensaje','fecha_emision_siat']));
    }
};
