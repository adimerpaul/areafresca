<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;

return new class extends Migration
{
    private array $permisos = [
        'Ver Almacenes', 'Crear Almacenes', 'Editar Almacenes',
        'Aplicar Almacenes', 'Anular Almacenes',
    ];

    public function up(): void
    {
        // Ingreso a almacÃ©n: se arma como BORRADOR editable y reciÃ©n al aplicarlo
        // crea los lotes y suma al stock de los productos.
        Schema::create('almacenes', function (Blueprint $table) {
            $table->id();
            $table->string('numero')->nullable()->index();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('usuario_nombre');
            $table->string('descripcion')->nullable();
            $table->string('estado', 20)->default('BORRADOR')->index();   // BORRADOR | APLICADO | ANULADO
            $table->text('observacion')->nullable();
            $table->decimal('total_cantidad', 14, 3)->default(0);
            $table->decimal('total_costo', 14, 2)->default(0);
            $table->timestamp('fecha')->index();
            $table->timestamp('fecha_aplicado')->nullable();
            $table->foreignId('aplicado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->string('aplicado_por_nombre')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('almacen_detalles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('almacen_id')->constrained('almacenes')->cascadeOnDelete();
            $table->foreignId('producto_id')->nullable()->constrained('productos')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('usuario_nombre')->nullable();   // quiÃ©n cargÃ³ esta lÃ­nea
            $table->string('codigo', 50);
            $table->string('nombre');
            $table->string('unidad', 20);
            $table->string('foto')->nullable();
            $table->string('lote')->nullable()->index();
            $table->date('fecha_vencimiento')->nullable()->index();
            $table->decimal('cantidad', 12, 3);
            $table->decimal('precio_compra', 12, 4)->default(0);
            $table->decimal('total', 14, 2)->default(0);
            $table->string('observacion')->nullable();
            $table->timestamps();
        });

        // Los lotes ya no vienen sÃ³lo de compras: ahora tambiÃ©n de un ingreso a almacÃ©n.
        Schema::table('lotes', function (Blueprint $table) {
            $table->foreignId('compra_detalle_id')->nullable()->change();
            $table->foreignId('almacen_detalle_id')->nullable()->after('compra_detalle_id')
                ->constrained('almacen_detalles')->nullOnDelete();
        });

        foreach ($this->permisos as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        $permissionsTable = config('permission.table_names.permissions');
        DB::table($permissionsTable)->whereIn('name', $this->permisos)->update(['grupo' => 'AlmacÃ©n', 'orden' => 7]);
        DB::table($permissionsTable)->where('name', 'Gestionar ConfiguraciÃ³n')->update(['orden' => 8]);

        User::where('username', 'admin')->first()?->givePermissionTo($this->permisos);
    }

    public function down(): void
    {
        Schema::table('lotes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('almacen_detalle_id');
        });

        Schema::dropIfExists('almacen_detalles');
        Schema::dropIfExists('almacenes');

        Permission::whereIn('name', $this->permisos)->delete();
        DB::table(config('permission.table_names.permissions'))
            ->where('name', 'Gestionar ConfiguraciÃ³n')->update(['orden' => 7]);
    }
};
