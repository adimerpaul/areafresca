<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Libro de ventas del SIAT importado desde Excel. Es un registro histórico
 * aparte de `ventas`: aquí entran las facturas que Impuestos ya reconoce,
 * vengan o no de este sistema.
 *
 * El CUF (columna "CÓDIGO DE AUTORIZACIÓN") es la llave natural: lleva índice
 * único para que reimportar el mismo archivo, o uno con meses solapados, no
 * duplique ninguna factura.
 */
return new class extends Migration
{
    private array $permisos = ['Ver Facturación', 'Importar Facturación', 'Eliminar Facturación'];

    public function up(): void
    {
        Schema::create('facturaciones', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('nro')->nullable();                 // "Nº" de la fila en el archivo
            $table->date('fecha_factura')->index();
            $table->string('numero_factura', 30)->index();
            $table->string('cuf', 100)->unique();                       // código de autorización
            $table->string('nit_ci_cliente', 30)->nullable()->index();
            $table->string('complemento', 10)->nullable();
            $table->string('razon_social', 255)->nullable();
            $table->decimal('importe_total', 14, 2)->default(0);
            $table->decimal('importe_ice', 14, 2)->default(0);
            $table->decimal('importe_iehd', 14, 2)->default(0);
            $table->decimal('importe_ipj', 14, 2)->default(0);
            $table->decimal('tasas', 14, 2)->default(0);
            $table->decimal('otros_no_sujetos_iva', 14, 2)->default(0);
            $table->decimal('exportaciones_exentas', 14, 2)->default(0);
            $table->decimal('ventas_tasa_cero', 14, 2)->default(0);
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('descuentos', 14, 2)->default(0);
            $table->decimal('importe_gift_card', 14, 2)->default(0);
            $table->decimal('importe_base_debito_fiscal', 14, 2)->default(0);
            $table->decimal('debito_fiscal', 14, 2)->default(0);
            $table->string('estado', 20)->default('VALIDA')->index();   // VALIDA / ANULADA
            $table->string('codigo_control', 30)->nullable();
            $table->string('tipo_venta', 30)->nullable();
            $table->string('credito_fiscal', 5)->nullable();            // SI / NO
            $table->string('estado_consolidacion', 30)->nullable();
            $table->string('archivo_origen')->nullable();               // nombre del xlsx importado
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            // El listado siempre filtra por mes y ordena por fecha.
            $table->index(['fecha_factura', 'estado']);
        });

        foreach ($this->permisos as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        DB::table(config('permission.table_names.permissions'))
            ->whereIn('name', $this->permisos)->update(['grupo' => 'Facturación', 'orden' => 8]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        User::where('username', 'admin')->first()?->givePermissionTo($this->permisos);
    }

    public function down(): void
    {
        Schema::dropIfExists('facturaciones');

        Permission::whereIn('name', $this->permisos)->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
