<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('licencias_medicas_importaciones')) {
            return;
        }

        Schema::table('licencias_medicas_importaciones', function (Blueprint $table) {
            if (! Schema::hasColumn('licencias_medicas_importaciones', 'dimension_estado')) {
                $table->string('dimension_estado', 30)->nullable()->index()->after('tipo');
            }
            if (! Schema::hasColumn('licencias_medicas_importaciones', 'huella_prevalidacion')) {
                $table->char('huella_prevalidacion', 64)->nullable()->after('resumen_json');
            }
            if (! Schema::hasColumn('licencias_medicas_importaciones', 'prevalidado_at')) {
                $table->timestamp('prevalidado_at')->nullable()->after('estado');
            }
            if (! Schema::hasColumn('licencias_medicas_importaciones', 'confirmado_at')) {
                $table->timestamp('confirmado_at')->nullable()->after('prevalidado_at');
            }
            if (! Schema::hasColumn('licencias_medicas_importaciones', 'revertido_at')) {
                $table->timestamp('revertido_at')->nullable()->after('confirmado_at');
            }
            if (! Schema::hasColumn('licencias_medicas_importaciones', 'revertido_por')) {
                $table->unsignedBigInteger('revertido_por')->nullable()->index()->after('revertido_at');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('licencias_medicas_importaciones')) {
            return;
        }

        Schema::table('licencias_medicas_importaciones', function (Blueprint $table) {
            foreach ([
                'revertido_por',
                'revertido_at',
                'confirmado_at',
                'prevalidado_at',
                'huella_prevalidacion',
                'dimension_estado',
            ] as $column) {
                if (Schema::hasColumn('licencias_medicas_importaciones', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
