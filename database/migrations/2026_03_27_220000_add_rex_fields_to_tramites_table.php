<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tramites', function (Blueprint $table) {
            if (!Schema::hasColumn('tramites', 'anulado_motivo')) {
                $table->text('anulado_motivo')->nullable()->after('anulado_por_user_id');
            }
            if (!Schema::hasColumn('tramites', 'rex_habilitado_at')) {
                $table->timestamp('rex_habilitado_at')->nullable()->after('calculo_periodos_data');
            }
            if (!Schema::hasColumn('tramites', 'rex_data')) {
                $table->json('rex_data')->nullable()->after('rex_habilitado_at');
            }
            if (!Schema::hasColumn('tramites', 'resolucion_docx_path')) {
                $table->string('resolucion_docx_path')->nullable()->after('rex_data');
            }
            if (!Schema::hasColumn('tramites', 'resolucion_pdf_path')) {
                $table->string('resolucion_pdf_path')->nullable()->after('resolucion_docx_path');
            }
            if (!Schema::hasColumn('tramites', 'resolucion_pdf_uploaded_at')) {
                $table->timestamp('resolucion_pdf_uploaded_at')->nullable()->after('resolucion_pdf_path');
            }
            if (!Schema::hasColumn('tramites', 'resultado_enviado_at')) {
                $table->timestamp('resultado_enviado_at')->nullable()->after('resolucion_pdf_uploaded_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tramites', function (Blueprint $table) {
            $drops = [];
            foreach (['anulado_motivo','rex_habilitado_at','rex_data','resolucion_docx_path','resolucion_pdf_path','resolucion_pdf_uploaded_at','resultado_enviado_at'] as $column) {
                if (Schema::hasColumn('tramites', $column)) {
                    $drops[] = $column;
                }
            }
            if (!empty($drops)) {
                $table->dropColumn($drops);
            }
        });
    }
};
