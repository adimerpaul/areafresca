<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Permite volver a emitir en Impuestos una factura cuyo envío falló y que el
 * SIN nunca aceptó (sin CUF o en ERROR_ENVIO). Antes la única salida era
 * convertirla en recibo; ahora se puede reintentar la emisión, una por una o
 * todas las del filtro de golpe.
 */
return new class extends Migration
{
    private const PERMISSION = 'Reemitir Factura';

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
