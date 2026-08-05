<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Cada permiso lleva su grupo y su orden de apariciÃ³n, para que el formulario
     * de usuarios se arme desde la base y no desde una lista fija en el frontend.
     */
    private array $groups = [
        'Inicio' => ['Ver EstadÃ­sticas'],
        'Usuarios' => ['Ver Usuarios', 'Crear Usuarios', 'Editar Usuarios', 'Eliminar Usuarios', 'Gestionar Permisos'],
        'Productos' => ['Ver Productos', 'Crear Productos', 'Editar Productos', 'Eliminar Productos', 'Editar Stock Inicial'],
        'Ventas' => ['Ver Ventas', 'Crear Ventas', 'Anular Ventas'],
        'Compras' => ['Ver Compras', 'Crear Compras'],
        'ConfiguraciÃ³n' => ['Gestionar ConfiguraciÃ³n'],
    ];

    public function up(): void
    {
        $table = config('permission.table_names.permissions');

        Schema::table($table, function (Blueprint $blueprint) {
            $blueprint->string('grupo', 60)->default('Otros')->after('guard_name');
            $blueprint->unsignedSmallInteger('orden')->default(99)->after('grupo');
        });

        foreach (array_keys($this->groups) as $index => $group) {
            DB::table($table)->whereIn('name', $this->groups[$group])
                ->update(['grupo' => $group, 'orden' => $index + 1]);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::table(config('permission.table_names.permissions'), function (Blueprint $blueprint) {
            $blueprint->dropColumn(['grupo', 'orden']);
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
