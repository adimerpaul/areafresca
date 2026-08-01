<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('clientes', function (Blueprint $table) {
            $table->id();
            $table->string('tipo_documento', 10)->default('CI');
            $table->string('numero_documento', 30);
            $table->string('complemento', 10)->nullable();
            $table->string('nombre');
            $table->string('email')->nullable();
            $table->string('telefono', 80)->nullable();
            $table->string('direccion')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['tipo_documento', 'numero_documento']);
        });

        Schema::table('ventas', function (Blueprint $table) {
            $table->foreignId('cliente_id')->nullable()->after('user_id')->constrained('clientes')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ventas', fn (Blueprint $table) => $table->dropConstrainedForeignId('cliente_id'));
        Schema::dropIfExists('clientes');
    }
};
