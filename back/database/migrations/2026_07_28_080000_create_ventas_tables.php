<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ventas', function (Blueprint $table) {
            $table->id();
            $table->string('numero')->nullable()->index();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('usuario_nombre');
            $table->decimal('subtotal', 12, 2);
            $table->decimal('descuento', 12, 2)->default(0);
            $table->decimal('total', 12, 2);
            $table->string('estado', 30)->default('COMPLETADA')->index();
            $table->text('observacion')->nullable();
            $table->timestamp('fecha');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('venta_detalles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venta_id')->constrained('ventas')->cascadeOnDelete();
            $table->foreignId('producto_id')->nullable()->constrained('productos')->nullOnDelete();
            $table->string('codigo', 50);
            $table->string('codigo_barras', 100)->nullable();
            $table->string('nombre');
            $table->string('categoria', 100)->nullable();
            $table->string('unidad', 20);
            $table->string('foto')->nullable();
            $table->decimal('precio_compra', 12, 2);
            $table->decimal('precio_venta', 12, 4);
            $table->unsignedInteger('cantidad');
            $table->decimal('subtotal', 12, 2);
            $table->decimal('descuento', 12, 2)->default(0);
            $table->decimal('total', 12, 2);
            $table->timestamps();
            $table->softDeletes();
        });

        foreach (['Ver Ventas', 'Crear Ventas', 'Anular Ventas'] as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }
        User::where('username', 'admin')->first()?->givePermissionTo(['Ver Ventas', 'Crear Ventas', 'Anular Ventas']);
    }

    public function down(): void
    {
        Schema::dropIfExists('venta_detalles');
        Schema::dropIfExists('ventas');
    }
};
