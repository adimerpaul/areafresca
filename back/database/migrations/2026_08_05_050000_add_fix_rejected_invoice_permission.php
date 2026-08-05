<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Permite corregir los datos del cliente y volver a emitir una factura que
 * Impuestos rechazó (estado OBSERVADA), por ejemplo cuando el NIT no existe
 * en el padrón del SIN y se cargó en lugar del CI.
 */
return new class extends Migration
{
    private const PERMISSION = 'Corregir Factura Rechazada';

    public function up(): void
    {
        $permission = Permission::firstOrCreate(['name' => self::PERMISSION, 'guard_name' => 'web']);

        DB::table(config('permission.table_names.permissions'))
            ->where('id', $permission->id)
            ->update(['grupo' => 'Ventas', 'orden' => 4]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        User::where('username', 'admin')->first()?->givePermissionTo(self::PERMISSION);
    }

    public function down(): void
    {
        Permission::where('name', self::PERMISSION)->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
