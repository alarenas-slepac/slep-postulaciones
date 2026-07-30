<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {

    private function indexExists(string $table, string $indexName): bool
    {
        $db = DB::getDatabaseName();

        return !empty(DB::select(
            "SELECT 1
             FROM information_schema.statistics
             WHERE table_schema = ?
               AND table_name = ?
               AND index_name = ?
             LIMIT 1",
            [$db, $table, $indexName]
        ));
    }

    public function up(): void
    {
        // 1) Columnas (si faltan)
        Schema::table('solicitudes_reemplazo', function (Blueprint $table) {

            if (!Schema::hasColumn('solicitudes_reemplazo', 'contrato_trabajo_docx_path')) {
                $table->string('contrato_trabajo_docx_path')->nullable()->after('orden_trabajo_creada_at');
            }

            if (!Schema::hasColumn('solicitudes_reemplazo', 'contrato_trabajo_postulant_profile_id')) {
                $table->unsignedBigInteger('contrato_trabajo_postulant_profile_id')
                    ->nullable()
                    ->after('contrato_trabajo_docx_path');
            }

            if (!Schema::hasColumn('solicitudes_reemplazo', 'contrato_trabajo_fecha_inicio_trabajo')) {
                $table->date('contrato_trabajo_fecha_inicio_trabajo')
                    ->nullable()
                    ->after('contrato_trabajo_postulant_profile_id');
            }

            if (!Schema::hasColumn('solicitudes_reemplazo', 'contrato_trabajo_is_final')) {
                $table->boolean('contrato_trabajo_is_final')
                    ->default(false)
                    ->after('contrato_trabajo_fecha_inicio_trabajo');
            }

            if (!Schema::hasColumn('solicitudes_reemplazo', 'contrato_trabajo_subido_por_user_id')) {
                $table->unsignedBigInteger('contrato_trabajo_subido_por_user_id')
                    ->nullable()
                    ->after('contrato_trabajo_is_final');
            }

            if (!Schema::hasColumn('solicitudes_reemplazo', 'contrato_trabajo_subido_at')) {
                $table->timestamp('contrato_trabajo_subido_at')
                    ->nullable()
                    ->after('contrato_trabajo_subido_por_user_id');
            }

            // AAEE: categoría para calcular valor hora
            if (!Schema::hasColumn('solicitudes_reemplazo', 'aaee_categoria')) {
                $table->string('aaee_categoria')
                    ->nullable()
                    ->after('contrato_trabajo_subido_at');
            }
        });

        // 2) Índices (si faltan) — con nombres cortos (<=64) y sin duplicar los que ya existen
        Schema::table('solicitudes_reemplazo', function (Blueprint $table) {

            if (Schema::hasColumn('solicitudes_reemplazo', 'contrato_trabajo_postulant_profile_id')
                && !$this->indexExists('solicitudes_reemplazo', 'sr_ct_profile_id_idx')
            ) {
                $table->index('contrato_trabajo_postulant_profile_id', 'sr_ct_profile_id_idx');
            }

            if (Schema::hasColumn('solicitudes_reemplazo', 'contrato_trabajo_fecha_inicio_trabajo')
                && !$this->indexExists('solicitudes_reemplazo', 'sr_ct_fecha_ini_idx')
            ) {
                $table->index('contrato_trabajo_fecha_inicio_trabajo', 'sr_ct_fecha_ini_idx');
            }

            if (Schema::hasColumn('solicitudes_reemplazo', 'contrato_trabajo_subido_por_user_id')
                && !$this->indexExists('solicitudes_reemplazo', 'sr_ct_subido_por_idx')
            ) {
                $table->index('contrato_trabajo_subido_por_user_id', 'sr_ct_subido_por_idx');
            }

            if (Schema::hasColumn('solicitudes_reemplazo', 'aaee_categoria')
                && !$this->indexExists('solicitudes_reemplazo', 'sr_aaee_cat_idx')
            ) {
                $table->index('aaee_categoria', 'sr_aaee_cat_idx');
            }
        });
    }

    public function down(): void
    {
        Schema::table('solicitudes_reemplazo', function (Blueprint $table) {

            foreach (['sr_ct_profile_id_idx', 'sr_ct_fecha_ini_idx', 'sr_ct_subido_por_idx', 'sr_aaee_cat_idx'] as $idx) {
                if ($this->indexExists('solicitudes_reemplazo', $idx)) {
                    $table->dropIndex($idx);
                }
            }

            foreach (
                [
                    'aaee_categoria',
                    'contrato_trabajo_subido_at',
                    'contrato_trabajo_subido_por_user_id',
                    'contrato_trabajo_is_final',
                    'contrato_trabajo_fecha_inicio_trabajo',
                    'contrato_trabajo_postulant_profile_id',
                    'contrato_trabajo_docx_path',
                ] as $col
            ) {
                if (Schema::hasColumn('solicitudes_reemplazo', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};