<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('bolsa_trabajo_ofertas')) {
            Schema::table('bolsa_trabajo_ofertas', function (Blueprint $table) {
                if (!Schema::hasColumn('bolsa_trabajo_ofertas', 'etapa_estado')) {
                    $table->string('etapa_estado', 40)->default('recepcion_antecedentes')->after('bases_pdf_original_name');
                }
                if (!Schema::hasColumn('bolsa_trabajo_ofertas', 'selected_postulacion_id')) {
                    $table->foreignId('selected_postulacion_id')->nullable()->after('etapa_estado')
                        ->constrained('bolsa_trabajo_postulaciones')->nullOnDelete();
                }
                if (!Schema::hasColumn('bolsa_trabajo_ofertas', 'etapa_changed_at')) {
                    $table->timestamp('etapa_changed_at')->nullable()->after('selected_postulacion_id');
                }
            });
        }

        if (Schema::hasTable('bolsa_trabajo_postulaciones')) {
            Schema::table('bolsa_trabajo_postulaciones', function (Blueprint $table) {
                if (!Schema::hasColumn('bolsa_trabajo_postulaciones', 'avanza_etapa')) {
                    $table->boolean('avanza_etapa')->default(false)->after('observacion');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('bolsa_trabajo_ofertas')) {
            Schema::table('bolsa_trabajo_ofertas', function (Blueprint $table) {
                if (Schema::hasColumn('bolsa_trabajo_ofertas', 'selected_postulacion_id')) {
                    $table->dropConstrainedForeignId('selected_postulacion_id');
                }
                if (Schema::hasColumn('bolsa_trabajo_ofertas', 'etapa_changed_at')) {
                    $table->dropColumn('etapa_changed_at');
                }
                if (Schema::hasColumn('bolsa_trabajo_ofertas', 'etapa_estado')) {
                    $table->dropColumn('etapa_estado');
                }
            });
        }

        if (Schema::hasTable('bolsa_trabajo_postulaciones')) {
            Schema::table('bolsa_trabajo_postulaciones', function (Blueprint $table) {
                if (Schema::hasColumn('bolsa_trabajo_postulaciones', 'avanza_etapa')) {
                    $table->dropColumn('avanza_etapa');
                }
            });
        }
    }
};
