<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\User;
use Spatie\Permission\Models\Permission;

return new class extends Migration {
    public function up(): void
    {
        $permission = Permission::firstOrCreate(['name' => 'Corregir Fecha Factura SIAT', 'guard_name' => 'web']);
        User::where('username', 'admin')->first()?->givePermissionTo($permission);
    }

    public function down(): void
    {
        Permission::where('name', 'Corregir Fecha Factura SIAT')->delete();
    }
};
