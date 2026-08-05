<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('configuraciones', function (Blueprint $table) {
            $table->id();
            $table->string('nombre_empresa')->default('Area Fresca');
            $table->string('nit', 50)->nullable();
            $table->string('direccion')->nullable();
            $table->string('telefono', 80)->nullable();
            $table->timestamps();
        });
        DB::table('configuraciones')->insert(['nombre_empresa' => 'Area Fresca', 'created_at' => now(), 'updated_at' => now()]);
        Schema::table('compras', function (Blueprint $table) {
            $table->string('estado', 30)->default('COMPLETADA')->after('total')->index();
        });
        $permission = Permission::firstOrCreate(['name' => 'Gestionar Configuración', 'guard_name' => 'web']);
        User::where('username', 'admin')->first()?->givePermissionTo($permission);
    }

    public function down(): void
    {
        Schema::table('compras', fn (Blueprint $table) => $table->dropColumn('estado'));
        Schema::dropIfExists('configuraciones');
    }
};
