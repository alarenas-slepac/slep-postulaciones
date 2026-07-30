<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cometidos_notificaciones_configuracion')) {
            Schema::create('cometidos_notificaciones_configuracion', function (Blueprint $table) {
                $table->id();
                $table->string('clave', 120)->unique();
                $table->string('nombre', 180);
                $table->text('descripcion')->nullable();
                $table->text('correos');
                $table->boolean('activo')->default(true);
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();

                $table->foreign('updated_by', 'com_notif_conf_user_fk')
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();
            });
        }

        DB::table('cometidos_notificaciones_configuracion')->updateOrInsert(
            ['clave' => 'servicios_generales_vehiculo_institucional'],
            [
                'nombre' => 'Servicios Generales - vehículo institucional',
                'descripcion' => 'Correo(s) que reciben aviso cuando un cometido autorizado contempla uso de vehículo institucional.',
                'correos' => 'johanna.isla@slepandaliencosta.gob.cl',
                'activo' => true,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        Schema::table('cometidos_funcionarios', function (Blueprint $table) {
            if (! Schema::hasColumn('cometidos_funcionarios', 'ssgg_notificado_vehiculo_at')) {
                $table->timestamp('ssgg_notificado_vehiculo_at')->nullable()->after('requiere_pasaje_aereo');
            }
            if (! Schema::hasColumn('cometidos_funcionarios', 'ssgg_notificado_vehiculo_email')) {
                $table->string('ssgg_notificado_vehiculo_email', 500)->nullable()->after('ssgg_notificado_vehiculo_at');
            }
            if (! Schema::hasColumn('cometidos_funcionarios', 'ssgg_notificado_vehiculo_por')) {
                $table->unsignedBigInteger('ssgg_notificado_vehiculo_por')->nullable()->after('ssgg_notificado_vehiculo_email');
            }
        });

        if (Schema::hasColumn('cometidos_funcionarios', 'ssgg_notificado_vehiculo_por')) {
            Schema::table('cometidos_funcionarios', function (Blueprint $table) {
                $table->foreign('ssgg_notificado_vehiculo_por', 'com_sg_veh_user_fk')
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::table('cometidos_funcionarios', function (Blueprint $table) {
            if (Schema::hasColumn('cometidos_funcionarios', 'ssgg_notificado_vehiculo_por')) {
                $table->dropForeign('com_sg_veh_user_fk');
            }
            if (Schema::hasColumn('cometidos_funcionarios', 'ssgg_notificado_vehiculo_por')) {
                $table->dropColumn('ssgg_notificado_vehiculo_por');
            }
            if (Schema::hasColumn('cometidos_funcionarios', 'ssgg_notificado_vehiculo_email')) {
                $table->dropColumn('ssgg_notificado_vehiculo_email');
            }
            if (Schema::hasColumn('cometidos_funcionarios', 'ssgg_notificado_vehiculo_at')) {
                $table->dropColumn('ssgg_notificado_vehiculo_at');
            }
        });

        Schema::dropIfExists('cometidos_notificaciones_configuracion');
    }
};
