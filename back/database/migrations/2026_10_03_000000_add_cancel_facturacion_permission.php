<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;

return new class extends Migration
{
    public function up(): void
    {
        $permission = Permission::firstOrCreate(['name' => 'Anular Facturación', 'guard_name' => 'web']);
        User::where('username', 'admin')->first()?->givePermissionTo($permission);
    }

    public function down(): void
    {
        Permission::where('name', 'Anular Facturación')->delete();
    }
};
