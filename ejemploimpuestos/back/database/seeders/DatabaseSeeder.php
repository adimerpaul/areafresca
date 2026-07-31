<?php

namespace Database\Seeders;

use App\Models\Cliente;
use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void{
        User::insert([
            [
                'name' => 'Administrador',
                'username' => 'admin',
                'agencia' => 'Divina',
                'role' => 'Admin',
                'email' => 'admin@example.com',
                'password' => Hash::make('admin123Admin'),
                'active' => '1',
                'avatar' => 'default.png',
                'created_at' => now(),
                'updated_at' => now(),
            ],
//            [
//                'name' => 'Adimer Paul Chambi Ajata',
//                'username' => 'adimer',
//                'agencia' => 'Challgua',
//                'role' => 'Usuario',
//                'email' => 'adimer@example.com',
//                'password' => Hash::make('adimer123Adimer'),
//                'active' => '1',
//                'avatar' => 'default.png',
//                'created_at' => now(),
//                'updated_at' => now(),
//            ],
        ]);

        $sqlFile = base_path('database/seeders/productos_202504240447.sql');
        $sqlContent = file_get_contents($sqlFile);
        DB::unprepared($sqlContent);
        error_log("Productos seed executed");

        $sqlFile = base_path('database/seeders/categorias_202504230342.sql');
        $sqlContent = file_get_contents($sqlFile);
        DB::unprepared($sqlContent);
        error_log("Categorias seed executed");

        $sqlFile = base_path('database/seeders/subcategorias_202504230342.sql');
        $sqlContent = file_get_contents($sqlFile);
        DB::unprepared($sqlContent);
        error_log("Subcategorias seed executed");

//        $table->string('nombre')->nullable();
//        $table->string('ci')->nullable();
//        $table->string('telefono')->nullable();
//        $table->string('direccion')->nullable();
//        $table->string('complemento')->nullable();
//        $table->string('codigoTipoDocumentoIdentidad')->nullable();
//        $table->string('email')->nullable();
        $cliente = Cliente::create([
            'nombre' => 'SN',
            'ci' => '0',
            'telefono' => '0',
            'complemento' => '0',
            'codigoTipoDocumentoIdentidad' => '1',
            'direccion' => 'S/N',
            'email' => '',
        ]);
    }
}
