<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;

return new class extends Migration
{
    public function up(): void
    {
        $permissions = [
            'Ver Usuarios', 'Crear Usuarios', 'Editar Usuarios', 'Eliminar Usuarios',
            'Gestionar Permisos', 'Ver Productos', 'Crear Productos',
            'Editar Productos', 'Eliminar Productos',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        $admin = User::firstOrCreate(['username' => 'admin'], [
            'name' => 'ADMINISTRADOR AREA FRESCA',
            'email' => 'admin@areafresca.bo',
            'ci' => '00000000',
            'password' => bcrypt('admin'),
        ]);
        $admin->syncPermissions(Permission::all());
    }

    public function down(): void
    {
        User::where('username', 'admin')->delete();
    }
};
