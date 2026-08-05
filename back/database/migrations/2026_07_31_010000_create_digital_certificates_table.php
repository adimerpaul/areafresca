<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('certificados_digitales', function (Blueprint $table) {
            $table->id();
            $table->string('nombre_archivo');
            $table->string('numero_serie')->nullable();
            $table->string('huella_sha256', 95);
            $table->json('titular')->nullable();
            $table->json('emisor')->nullable();
            $table->dateTime('valido_desde')->nullable();
            $table->dateTime('valido_hasta')->nullable()->index();
            $table->longText('archivo_p12_cifrado');
            $table->longText('clave_privada_cifrada');
            $table->longText('clave_publica');
            $table->longText('certificado_pem');
            $table->text('contrasena_interna_cifrada');
            $table->string('ruta_directorio')->nullable();
            $table->boolean('activo')->default(true)->index();
            $table->foreignId('creado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certificados_digitales');
    }
};
