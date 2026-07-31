<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            Schema::table('certificados_digitales', function (Blueprint $table) {
                $table->index('huella_sha256');
            });
            return;
        }

        $indexes = collect(DB::select('SHOW INDEX FROM certificados_digitales'));
        if ($indexes->contains(fn ($index) => $index->Key_name === 'certificados_digitales_huella_sha256_unique')) {
            Schema::table('certificados_digitales', fn (Blueprint $table) => $table->dropUnique('certificados_digitales_huella_sha256_unique'));
        }
        $indexes = collect(DB::select('SHOW INDEX FROM certificados_digitales'));
        if (! $indexes->contains(fn ($index) => $index->Column_name === 'huella_sha256')) {
            Schema::table('certificados_digitales', fn (Blueprint $table) => $table->index('huella_sha256'));
        }
    }

    public function down(): void
    {
        Schema::table('certificados_digitales', function (Blueprint $table) {
            $table->dropIndex(['huella_sha256']);
            $table->unique('huella_sha256');
        });
    }
};
