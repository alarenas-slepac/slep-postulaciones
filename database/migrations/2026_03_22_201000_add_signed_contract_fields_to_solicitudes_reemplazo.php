<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('solicitudes_reemplazo', function (Blueprint $table) {
            if (!Schema::hasColumn('solicitudes_reemplazo', 'contrato_trabajo_firmado_pdf_path')) {
                $table->string('contrato_trabajo_firmado_pdf_path')->nullable()->after('contrato_trabajo_subido_at');
            }
            if (!Schema::hasColumn('solicitudes_reemplazo', 'contrato_trabajo_firmado_subido_por_user_id')) {
                $table->unsignedBigInteger('contrato_trabajo_firmado_subido_por_user_id')->nullable()->after('contrato_trabajo_firmado_pdf_path');
            }
            if (!Schema::hasColumn('solicitudes_reemplazo', 'contrato_trabajo_firmado_subido_at')) {
                $table->timestamp('contrato_trabajo_firmado_subido_at')->nullable()->after('contrato_trabajo_firmado_subido_por_user_id');
            }
            if (!Schema::hasColumn('solicitudes_reemplazo', 'contrato_trabajo_firmado_enviado_por_user_id')) {
                $table->unsignedBigInteger('contrato_trabajo_firmado_enviado_por_user_id')->nullable()->after('contrato_trabajo_firmado_subido_at');
            }
            if (!Schema::hasColumn('solicitudes_reemplazo', 'contrato_trabajo_firmado_enviado_at')) {
                $table->timestamp('contrato_trabajo_firmado_enviado_at')->nullable()->after('contrato_trabajo_firmado_enviado_por_user_id');
            }
            if (!Schema::hasColumn('solicitudes_reemplazo', 'cerrado_por_user_id')) {
                $table->unsignedBigInteger('cerrado_por_user_id')->nullable()->after('contrato_trabajo_firmado_enviado_at');
            }
            if (!Schema::hasColumn('solicitudes_reemplazo', 'cerrado_at')) {
                $table->timestamp('cerrado_at')->nullable()->after('cerrado_por_user_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('solicitudes_reemplazo', function (Blueprint $table) {
            foreach ([
                'cerrado_at',
                'cerrado_por_user_id',
                'contrato_trabajo_firmado_enviado_at',
                'contrato_trabajo_firmado_enviado_por_user_id',
                'contrato_trabajo_firmado_subido_at',
                'contrato_trabajo_firmado_subido_por_user_id',
                'contrato_trabajo_firmado_pdf_path',
            ] as $column) {
                if (Schema::hasColumn('solicitudes_reemplazo', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
