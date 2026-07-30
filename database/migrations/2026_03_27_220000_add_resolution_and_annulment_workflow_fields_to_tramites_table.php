<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tramites', function (Blueprint $table) {
            if (!Schema::hasColumn('tramites', 'anulado_motivo')) {
                $table->text('anulado_motivo')->nullable()->after('anulado_por_user_id');
            }
            if (!Schema::hasColumn('tramites', 'rex_generado_at')) {
                $table->timestamp('rex_generado_at')->nullable()->after('calculo_periodos_data');
            }
            if (!Schema::hasColumn('tramites', 'rex_docx_path')) {
                $table->string('rex_docx_path', 500)->nullable()->after('rex_generado_at');
            }
            if (!Schema::hasColumn('tramites', 'resolucion_pdf_path')) {
                $table->string('resolucion_pdf_path', 500)->nullable()->after('rex_docx_path');
            }
            if (!Schema::hasColumn('tramites', 'resolucion_pdf_uploaded_at')) {
                $table->timestamp('resolucion_pdf_uploaded_at')->nullable()->after('resolucion_pdf_path');
            }
            if (!Schema::hasColumn('tramites', 'resultado_enviado_at')) {
                $table->timestamp('resultado_enviado_at')->nullable()->after('resolucion_pdf_uploaded_at');
            }
            if (!Schema::hasColumn('tramites', 'resuelto_at')) {
                $table->timestamp('resuelto_at')->nullable()->after('resultado_enviado_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tramites', function (Blueprint $table) {
            $columns = [];
            foreach (['resuelto_at', 'resultado_enviado_at', 'resolucion_pdf_uploaded_at', 'resolucion_pdf_path', 'rex_docx_path', 'rex_generado_at', 'anulado_motivo'] as $column) {
                if (Schema::hasColumn('tramites', $column)) {
                    $columns[] = $column;
                }
            }
            if ($columns) {
                $table->dropColumn($columns);
            }
        });
    }
};
