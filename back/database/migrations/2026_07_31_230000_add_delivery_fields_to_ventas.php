<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->string('pdf_path')->nullable()->after('xml_path');
            $table->timestamp('email_enviado_en')->nullable()->after('pdf_path');
            $table->text('email_error')->nullable()->after('email_enviado_en');
        });
    }

    public function down(): void
    {
        Schema::table('ventas', fn (Blueprint $table) => $table->dropColumn(['pdf_path', 'email_enviado_en', 'email_error']));
    }
};
