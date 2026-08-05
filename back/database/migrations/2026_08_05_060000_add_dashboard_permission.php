<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Crea el permiso del panel de inicio. La migración de grupos ya lo nombraba,
 * pero sólo actualizaba grupos de permisos existentes y este nunca se creó, así
 * que el panel quedaba visible para cualquiera con "Ver Ventas" —incluidos los
 * vendedores, que no deberían ver ganancias ni comparativas por usuario.
 *
 * Se otorga a quienes hoy tienen todos los permisos, para no quitarle el panel
 * a los administradores al aplicar la migración.
 */
return new class extends Migration
{
    private const PERMISSION = 'Ver Estadísticas';

    public function up(): void
    {
        $table = config('permission.table_names.permissions');
        $before = DB::table($table)->pluck('id');

        $permission = Permission::firstOrCreate(['name' => self::PERMISSION, 'guard_name' => 'web']);

        DB::table($table)->where('id', $permission->id)->update(['grupo' => 'Inicio', 'orden' => 1]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $full = DB::table('model_has_permissions')
            ->whereIn('permission_id', $before)
            ->where('model_type', User::class)
            ->select('model_id')
            ->groupBy('model_id')
            ->havingRaw('COUNT(DISTINCT permission_id) = ?', [$before->count()])
            ->pluck('model_id');

        User::whereIn('id', $full)->each(fn (User $user) => $user->givePermissionTo(self::PERMISSION));
    }

    public function down(): void
    {
        Permission::where('name', self::PERMISSION)->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
