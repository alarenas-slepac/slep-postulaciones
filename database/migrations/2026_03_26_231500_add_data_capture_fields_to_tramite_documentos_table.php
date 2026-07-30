<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tramite_documentos', function (Blueprint $table) {
            if (!Schema::hasColumn('tramite_documentos', 'captura_estado')) {
                $table->string('captura_estado', 30)->nullable()->after('revision_observacion');
            }

            if (!Schema::hasColumn('tramite_documentos', 'captura_metodo')) {
                $table->string('captura_metodo', 30)->nullable()->after('captura_estado');
            }

            if (!Schema::hasColumn('tramite_documentos', 'captura_ejecutada_at')) {
                $table->timestamp('captura_ejecutada_at')->nullable()->after('captura_metodo');
            }

            if (!Schema::hasColumn('tramite_documentos', 'captura_rut')) {
                $table->string('captura_rut', 20)->nullable()->after('captura_ejecutada_at');
            }

            if (!Schema::hasColumn('tramite_documentos', 'captura_periodos')) {
                $table->longText('captura_periodos')->nullable()->after('captura_rut');
            }

            if (!Schema::hasColumn('tramite_documentos', 'captura_rango_inicio')) {
                $table->date('captura_rango_inicio')->nullable()->after('captura_periodos');
            }

            if (!Schema::hasColumn('tramite_documentos', 'captura_rango_termino')) {
                $table->date('captura_rango_termino')->nullable()->after('captura_rango_inicio');
            }

            if (!Schema::hasColumn('tramite_documentos', 'captura_total_periodos')) {
                $table->unsignedInteger('captura_total_periodos')->nullable()->after('captura_rango_termino');
            }

            if (!Schema::hasColumn('tramite_documentos', 'captura_tiene_interrupciones')) {
                $table->boolean('captura_tiene_interrupciones')->nullable()->after('captura_total_periodos');
            }

            if (!Schema::hasColumn('tramite_documentos', 'captura_comparacion_periodo')) {
                $table->string('captura_comparacion_periodo', 50)->nullable()->after('captura_tiene_interrupciones');
            }

            if (!Schema::hasColumn('tramite_documentos', 'captura_observaciones')) {
                $table->text('captura_observaciones')->nullable()->after('captura_comparacion_periodo');
            }

            if (!Schema::hasColumn('tramite_documentos', 'captura_payload')) {
                $table->longText('captura_payload')->nullable()->after('captura_observaciones');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tramite_documentos', function (Blueprint $table) {
            foreach ([
                'captura_payload',
                'captura_observaciones',
                'captura_comparacion_periodo',
                'captura_tiene_interrupciones',
                'captura_total_periodos',
                'captura_rango_termino',
                'captura_rango_inicio',
                'captura_periodos',
                'captura_rut',
                'captura_ejecutada_at',
                'captura_metodo',
                'captura_estado',
            ] as $column) {
                if (Schema::hasColumn('tramite_documentos', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
