<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('bolsa_trabajo_ofertas')) {
            return;
        }

        Schema::table('bolsa_trabajo_ofertas', function (Blueprint $table) {
            if (!Schema::hasColumn('bolsa_trabajo_ofertas', 'bases_pdf_path')) {
                $table->string('bases_pdf_path', 255)->nullable()->after('correo_contacto');
            }
            if (!Schema::hasColumn('bolsa_trabajo_ofertas', 'bases_pdf_original_name')) {
                $table->string('bases_pdf_original_name', 255)->nullable()->after('bases_pdf_path');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('bolsa_trabajo_ofertas')) {
            return;
        }

        Schema::table('bolsa_trabajo_ofertas', function (Blueprint $table) {
            if (Schema::hasColumn('bolsa_trabajo_ofertas', 'bases_pdf_original_name')) {
                $table->dropColumn('bases_pdf_original_name');
            }
            if (Schema::hasColumn('bolsa_trabajo_ofertas', 'bases_pdf_path')) {
                $table->dropColumn('bases_pdf_path');
            }
        });
    }
};
