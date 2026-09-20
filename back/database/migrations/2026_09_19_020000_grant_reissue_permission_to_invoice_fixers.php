<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * "Reemitir Factura" reemplaza al viejo apaño de convertir en recibo la factura
 * que no llegó a Impuestos, así que lo reciben los mismos que ya podían hacer
 * ese apaño: quien tenga "Cambiar Factura a Recibo". La migración que creó el
 * permiso sólo se lo dio a admin, y en la práctica la pantalla la usan otros.
 */
return new class extends Migration
{
    private const PERMISSION = 'Reemitir Factura';

    private const SOURCE = 'Cambiar Factura a Recibo';

    public function up(): void
    {
        $permission = Permission::firstOrCreate(['name' => self::PERMISSION, 'guard_name' => 'web']);

        User::whereHas('permissions', fn ($q) => $q->where('name', self::SOURCE))
            ->get()->each->givePermissionTo($permission);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        User::whereHas('permissions', fn ($q) => $q->where('name', self::SOURCE))
            ->get()->each->revokePermissionTo(self::PERMISSION);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
