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
        Schema::create('proveedores', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('nit', 30)->nullable();
            $table->string('telefono', 50)->nullable();
            $table->string('direccion')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('compras', function (Blueprint $table) {
            $table->id();
            $table->string('numero')->nullable()->index();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('usuario_nombre');
            $table->foreignId('proveedor_id')->nullable()->constrained('proveedores')->nullOnDelete();
            $table->string('proveedor_nombre');
            $table->string('numero_factura')->nullable()->index();
            $table->string('tipo_pago', 30);
            $table->text('comentario')->nullable();
            $table->decimal('total', 14, 2);
            $table->timestamp('fecha');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('compra_detalles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('compra_id')->constrained('compras')->cascadeOnDelete();
            $table->foreignId('producto_id')->nullable()->constrained('productos')->nullOnDelete();
            $table->string('codigo', 50);
            $table->string('nombre');
            $table->string('unidad', 20);
            $table->string('lote')->nullable()->index();
            $table->date('fecha_vencimiento')->nullable()->index();
            $table->decimal('cantidad', 12, 3);
            $table->decimal('precio_unitario', 12, 4);
            $table->decimal('total', 14, 2);
            $table->timestamps();
        });

        Schema::create('lotes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('producto_id')->constrained('productos')->cascadeOnDelete();
            $table->foreignId('compra_detalle_id')->constrained('compra_detalles')->cascadeOnDelete();
            $table->string('lote')->nullable()->index();
            $table->date('fecha_vencimiento')->nullable()->index();
            $table->decimal('cantidad_inicial', 12, 3);
            $table->decimal('cantidad_disponible', 12, 3);
            $table->timestamps();
            $table->index(['producto_id', 'fecha_vencimiento']);
        });

        Schema::create('venta_detalle_lotes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venta_detalle_id')->constrained('venta_detalles')->cascadeOnDelete();
            $table->foreignId('lote_id')->constrained('lotes')->cascadeOnDelete();
            $table->decimal('cantidad', 12, 3);
            $table->timestamps();
        });

        foreach (['Ver Compras', 'Crear Compras'] as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }
        User::where('username', 'admin')->first()?->givePermissionTo(['Ver Compras', 'Crear Compras']);
    }

    public function down(): void
    {
        Schema::dropIfExists('venta_detalle_lotes');
        Schema::dropIfExists('lotes');
        Schema::dropIfExists('compra_detalles');
        Schema::dropIfExists('compras');
        Schema::dropIfExists('proveedores');
    }
};
