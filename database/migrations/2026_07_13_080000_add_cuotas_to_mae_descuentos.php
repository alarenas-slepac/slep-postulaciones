<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('mae_cuotas_importaciones')) {
            Schema::create('mae_cuotas_importaciones', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('mae_carga_id');
                $table->string('columna_origen', 191);
                $table->string('columna_normalizada', 191);
                $table->string('nombre_archivo');
                $table->string('ruta_archivo');
                $table->string('estado', 50)->default('procesando');
                $table->unsignedInteger('total_filas')->default(0);
                $table->unsignedInteger('total_asociadas')->default(0);
                $table->unsignedInteger('total_errores')->default(0);
                $table->json('resumen_json')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamp('procesado_at')->nullable();
                $table->timestamps();

                $table->foreign('mae_carga_id', 'mci_carga_fk')
                    ->references('id')->on('mae_cargas')->cascadeOnDelete();
                $table->foreign('created_by', 'mci_user_fk')
                    ->references('id')->on('users')->nullOnDelete();
                $table->index(['mae_carga_id', 'columna_normalizada'], 'mci_carga_col_idx');
                $table->index(['estado', 'created_at'], 'mci_estado_fecha_idx');
            });
        }

        if (!Schema::hasTable('mae_cuotas_importacion_detalles')) {
            Schema::create('mae_cuotas_importacion_detalles', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('mae_cuotas_importacion_id');
                $table->unsignedBigInteger('mae_registro_descuento_id')->nullable();
                $table->unsignedInteger('numero_fila');
                $table->string('rut', 32)->nullable();
                $table->unsignedInteger('cuota_actual')->nullable();
                $table->unsignedInteger('total_cuotas')->nullable();
                $table->text('observacion')->nullable();
                $table->string('estado', 30);
                $table->text('mensaje')->nullable();
                $table->timestamps();

                $table->foreign('mae_cuotas_importacion_id', 'mcid_import_fk')
                    ->references('id')->on('mae_cuotas_importaciones')->cascadeOnDelete();
                $table->foreign('mae_registro_descuento_id', 'mcid_desc_fk')
                    ->references('id')->on('mae_registro_descuentos')->nullOnDelete();
                $table->index(['mae_cuotas_importacion_id', 'estado'], 'mcid_import_estado_idx');
                $table->index(['rut'], 'mcid_rut_idx');
            });
        }

        Schema::table('mae_registro_descuentos', function (Blueprint $table) {
            if (!Schema::hasColumn('mae_registro_descuentos', 'cuota_actual')) {
                $table->unsignedInteger('cuota_actual')->nullable()->after('valor');
            }
            if (!Schema::hasColumn('mae_registro_descuentos', 'total_cuotas')) {
                $table->unsignedInteger('total_cuotas')->nullable()->after('cuota_actual');
            }
            if (!Schema::hasColumn('mae_registro_descuentos', 'cuota_observacion')) {
                $table->text('cuota_observacion')->nullable()->after('total_cuotas');
            }
            if (!Schema::hasColumn('mae_registro_descuentos', 'cuota_importacion_id')) {
                $table->unsignedBigInteger('cuota_importacion_id')->nullable()->after('cuota_observacion');
            }
            if (!Schema::hasColumn('mae_registro_descuentos', 'cuota_updated_by')) {
                $table->unsignedBigInteger('cuota_updated_by')->nullable()->after('cuota_importacion_id');
            }
            if (!Schema::hasColumn('mae_registro_descuentos', 'cuota_updated_at')) {
                $table->timestamp('cuota_updated_at')->nullable()->after('cuota_updated_by');
            }
        });

        Schema::table('mae_registro_descuentos', function (Blueprint $table) {
            $table->foreign('cuota_importacion_id', 'mrd_cuota_import_fk')
                ->references('id')->on('mae_cuotas_importaciones')->nullOnDelete();
            $table->foreign('cuota_updated_by', 'mrd_cuota_user_fk')
                ->references('id')->on('users')->nullOnDelete();
            $table->index(['cuota_importacion_id'], 'mrd_cuota_import_idx');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('mae_registro_descuentos')) {
            Schema::table('mae_registro_descuentos', function (Blueprint $table) {
                $table->dropForeign('mrd_cuota_import_fk');
                $table->dropForeign('mrd_cuota_user_fk');
                $table->dropIndex('mrd_cuota_import_idx');
                $table->dropColumn([
                    'cuota_actual',
                    'total_cuotas',
                    'cuota_observacion',
                    'cuota_importacion_id',
                    'cuota_updated_by',
                    'cuota_updated_at',
                ]);
            });
        }

        Schema::dropIfExists('mae_cuotas_importacion_detalles');
        Schema::dropIfExists('mae_cuotas_importaciones');
    }
};
