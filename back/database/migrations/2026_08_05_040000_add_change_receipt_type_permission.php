<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Permite pasar a RECIBO las ventas marcadas como FACTURA que nunca llegaron a
 * Impuestos (sin CUF), para que dejen de figurar como facturas pendientes.
 */
return new class extends Migration
{
    private const PERMISSION = 'Cambiar Factura a Recibo';

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
