<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('licencias_medicas')) {
            Schema::table('licencias_medicas', function (Blueprint $table) {
                if (! Schema::hasColumn('licencias_medicas', 'vigencia')) {
                    $table->string('vigencia', 20)->nullable()->after('institucion_salud');
                }
                if (! Schema::hasColumn('licencias_medicas', 'valor_licencia')) {
                    $table->decimal('valor_licencia', 14, 2)->nullable()->after('tipo_licencia_glosa');
                }
                if (! Schema::hasColumn('licencias_medicas', 'se_puede_recuperar')) {
                    $table->string('se_puede_recuperar', 10)->nullable()->after('valor_licencia');
                }
                if (! Schema::hasColumn('licencias_medicas', 'primer_estado')) {
                    $table->string('primer_estado', 120)->nullable()->index()->after('se_puede_recuperar');
                }
                if (! Schema::hasColumn('licencias_medicas', 'segundo_estado')) {
                    $table->string('segundo_estado', 120)->nullable()->index()->after('primer_estado');
                }
                if (! Schema::hasColumn('licencias_medicas', 'fecha_revision')) {
                    $table->date('fecha_revision')->nullable()->after('segundo_estado');
                }
                if (! Schema::hasColumn('licencias_medicas', 'gestion_cobro')) {
                    $table->string('gestion_cobro', 120)->nullable()->index()->after('fecha_revision');
                }
                if (! Schema::hasColumn('licencias_medicas', 'numero_ord')) {
                    $table->string('numero_ord', 80)->nullable()->after('gestion_cobro');
                }
                if (! Schema::hasColumn('licencias_medicas', 'fecha_cobro')) {
                    $table->date('fecha_cobro')->nullable()->after('numero_ord');
                }
                if (! Schema::hasColumn('licencias_medicas', 'numero_ord_nuevo_cobro')) {
                    $table->string('numero_ord_nuevo_cobro', 80)->nullable()->after('fecha_cobro');
                }
                if (! Schema::hasColumn('licencias_medicas', 'fecha_nuevo_cobro')) {
                    $table->date('fecha_nuevo_cobro')->nullable()->after('numero_ord_nuevo_cobro');
                }
                if (! Schema::hasColumn('licencias_medicas', 'ingresar_siaper')) {
                    $table->string('ingresar_siaper', 120)->nullable()->after('fecha_nuevo_cobro');
                }
                if (! Schema::hasColumn('licencias_medicas', 'rex_siaper')) {
                    $table->string('rex_siaper', 120)->nullable()->after('ingresar_siaper');
                }
                if (! Schema::hasColumn('licencias_medicas', 'realizo_apelacion')) {
                    $table->string('realizo_apelacion', 80)->nullable()->after('rex_siaper');
                }
                if (! Schema::hasColumn('licencias_medicas', 'importacion_id')) {
                    $table->unsignedBigInteger('importacion_id')->nullable()->index()->after('realizo_apelacion');
                }
                if (! Schema::hasColumn('licencias_medicas', 'origen_planilla_anio')) {
                    $table->string('origen_planilla_anio', 10)->nullable()->after('importacion_id');
                }
            });
        }

        if (! Schema::hasTable('licencias_medicas_importaciones')) {
            Schema::create('licencias_medicas_importaciones', function (Blueprint $table) {
                $table->id();
                $table->string('tipo', 80)->index();
                $table->string('nombre_archivo');
                $table->string('archivo_path')->nullable();
                $table->string('periodo', 30)->nullable();
                $table->unsignedInteger('total_filas')->default(0);
                $table->unsignedInteger('total_importadas')->default(0);
                $table->unsignedInteger('total_actualizadas')->default(0);
                $table->unsignedInteger('total_omitidas')->default(0);
                $table->unsignedInteger('total_duplicadas')->default(0);
                $table->unsignedInteger('total_inconsistencias')->default(0);
                $table->json('resumen_json')->nullable();
                $table->string('estado', 40)->default('procesado')->index();
                $table->foreignId('subido_por')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('licencias_medicas_importaciones');

        if (! Schema::hasTable('licencias_medicas')) {
            return;
        }

        Schema::table('licencias_medicas', function (Blueprint $table) {
            foreach ([
                'origen_planilla_anio',
                'importacion_id',
                'realizo_apelacion',
                'rex_siaper',
                'ingresar_siaper',
                'fecha_nuevo_cobro',
                'numero_ord_nuevo_cobro',
                'fecha_cobro',
                'numero_ord',
                'gestion_cobro',
                'fecha_revision',
                'segundo_estado',
                'primer_estado',
                'se_puede_recuperar',
                'valor_licencia',
                'vigencia',
            ] as $column) {
                if (Schema::hasColumn('licencias_medicas', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
