<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('solicitudes_reemplazo', function (Blueprint $table) {
            if (! Schema::hasColumn('solicitudes_reemplazo', 'finiquito_firmado_pdf_path')) {
                $table->string('finiquito_firmado_pdf_path')->nullable()->after('finiquito_pdf_path');
            }
            if (! Schema::hasColumn('solicitudes_reemplazo', 'finiquito_firmado_nombre_original')) {
                $table->string('finiquito_firmado_nombre_original')->nullable()->after('finiquito_firmado_pdf_path');
            }
            if (! Schema::hasColumn('solicitudes_reemplazo', 'finiquito_firmado_mime')) {
                $table->string('finiquito_firmado_mime', 120)->nullable()->after('finiquito_firmado_nombre_original');
            }
            if (! Schema::hasColumn('solicitudes_reemplazo', 'finiquito_firmado_size')) {
                $table->unsignedBigInteger('finiquito_firmado_size')->nullable()->after('finiquito_firmado_mime');
            }
            if (! Schema::hasColumn('solicitudes_reemplazo', 'finiquito_firmado_observacion')) {
                $table->text('finiquito_firmado_observacion')->nullable()->after('finiquito_observacion');
            }
            if (! Schema::hasColumn('solicitudes_reemplazo', 'finiquito_firmado_cargado_por_user_id')) {
                $table->unsignedBigInteger('finiquito_firmado_cargado_por_user_id')->nullable()->after('finiquito_generado_por_user_id');
            }
            if (! Schema::hasColumn('solicitudes_reemplazo', 'finiquito_firmado_cargado_at')) {
                $table->timestamp('finiquito_firmado_cargado_at')->nullable()->after('finiquito_firmado_cargado_por_user_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('solicitudes_reemplazo', function (Blueprint $table) {
            foreach ([
                'finiquito_firmado_cargado_at',
                'finiquito_firmado_cargado_por_user_id',
                'finiquito_firmado_observacion',
                'finiquito_firmado_size',
                'finiquito_firmado_mime',
                'finiquito_firmado_nombre_original',
                'finiquito_firmado_pdf_path',
            ] as $column) {
                if (Schema::hasColumn('solicitudes_reemplazo', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
