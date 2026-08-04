<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;

return new class extends Migration
{
    /**
     * Motivos iniciales. Viven en tabla para poder agregar o desactivar motivos
     * sin tocar el cÃ³digo ni el frontend.
     */
    private array $motivos = [
        ['codigo' => 'CALIDAD', 'nombre' => 'Baja por calidad', 'color' => 'deep-orange'],
        ['codigo' => 'MERMA', 'nombre' => 'Baja por merma', 'color' => 'amber-9'],
        ['codigo' => 'USO_TIENDA', 'nombre' => 'Baja por uso de tienda', 'color' => 'blue-grey'],
        ['codigo' => 'CRUCE', 'nombre' => 'Baja por cruce', 'color' => 'purple'],
        ['codigo' => 'PERDIDA', 'nombre' => 'Baja por pÃ©rdida', 'color' => 'red'],
        ['codigo' => 'VENCIDO', 'nombre' => 'Baja por vencimiento', 'color' => 'brown'],
    ];

    private array $permisos = ['Ver Bajas', 'Crear Bajas', 'Anular Bajas'];

    public function up(): void
    {
        Schema::create('baja_motivos', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 40)->unique();
            $table->string('nombre', 120);
            $table->string('color', 30)->default('grey-8');
            $table->unsignedSmallInteger('orden')->default(99);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('bajas', function (Blueprint $table) {
            $table->id();
            $table->string('numero')->nullable()->index();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('usuario_nombre');
            $table->foreignId('motivo_id')->nullable()->constrained('baja_motivos')->nullOnDelete();
            $table->string('motivo', 120);          // snapshot: el motivo puede renombrarse despuÃ©s
            $table->decimal('total_costo', 14, 2)->default(0);
            $table->string('estado', 20)->default('REGISTRADA')->index();
            $table->text('observacion')->nullable();
            $table->timestamp('fecha')->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('baja_detalles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('baja_id')->constrained('bajas')->cascadeOnDelete();
            $table->foreignId('producto_id')->nullable()->constrained('productos')->nullOnDelete();
            $table->string('codigo', 50);
            $table->string('nombre');
            $table->string('unidad', 20);
            $table->string('foto')->nullable();
            $table->decimal('cantidad', 12, 3);
            $table->decimal('precio_compra', 12, 4)->default(0);
            $table->decimal('total', 14, 2)->default(0);
            $table->string('observacion')->nullable();
            $table->timestamps();
        });

        // Igual que en ventas: guarda de quÃ© lote saliÃ³ cada cantidad para poder devolverla al anular.
        Schema::create('baja_detalle_lotes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('baja_detalle_id')->constrained('baja_detalles')->cascadeOnDelete();
            $table->foreignId('lote_id')->constrained('lotes')->cascadeOnDelete();
            $table->decimal('cantidad', 12, 3);
            $table->timestamps();
        });

        foreach ($this->motivos as $index => $motivo) {
            DB::table('baja_motivos')->insert($motivo + [
                'orden' => $index + 1, 'activo' => true,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        foreach ($this->permisos as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        $permissionsTable = config('permission.table_names.permissions');
        DB::table($permissionsTable)->whereIn('name', $this->permisos)->update(['grupo' => 'Bajas', 'orden' => 6]);
        DB::table($permissionsTable)->where('name', 'Gestionar ConfiguraciÃ³n')->update(['orden' => 7]);

        User::where('username', 'admin')->first()?->givePermissionTo($this->permisos);
    }

    public function down(): void
    {
        Schema::dropIfExists('baja_detalle_lotes');
        Schema::dropIfExists('baja_detalles');
        Schema::dropIfExists('bajas');
        Schema::dropIfExists('baja_motivos');

        Permission::whereIn('name', $this->permisos)->delete();
        DB::table(config('permission.table_names.permissions'))
            ->where('name', 'Gestionar ConfiguraciÃ³n')->update(['orden' => 6]);
    }
};
