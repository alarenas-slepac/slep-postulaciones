<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('solicitudes_reemplazo', function (Blueprint $table) {
            $table->string('resolucion_docente_docx_path')->nullable();
            $table->unsignedBigInteger('resolucion_docente_generada_por_user_id')->nullable();
            $table->timestamp('resolucion_docente_generada_at')->nullable();
            $table->string('resolucion_docente_firmada_pdf_path')->nullable();
            $table->unsignedBigInteger('resolucion_docente_firmada_subida_por_user_id')->nullable();
            $table->timestamp('resolucion_docente_firmada_subida_at')->nullable();
            $table->unsignedBigInteger('resolucion_docente_notificada_por_user_id')->nullable();
            $table->timestamp('resolucion_docente_notificada_at')->nullable();
            $table->index('resolucion_docente_firmada_pdf_path');
        });
    }

    public function down(): void
    {
        Schema::table('solicitudes_reemplazo', function (Blueprint $table) {
            $table->dropIndex(['resolucion_docente_firmada_pdf_path']);
            $table->dropColumn([
                'resolucion_docente_docx_path', 'resolucion_docente_generada_por_user_id',
                'resolucion_docente_generada_at', 'resolucion_docente_firmada_pdf_path',
                'resolucion_docente_firmada_subida_por_user_id', 'resolucion_docente_firmada_subida_at',
                'resolucion_docente_notificada_por_user_id', 'resolucion_docente_notificada_at',
            ]);
        });
    }
};
