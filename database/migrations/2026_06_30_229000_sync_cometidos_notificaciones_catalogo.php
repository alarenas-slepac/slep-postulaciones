<?php

use App\Models\CometidoNotificacionConfiguracion;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
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
                $table->string('categoria', 120)->nullable();
                $table->string('tipo_destinatario', 80)->default('rol_configurable');
                $table->text('correos')->nullable();
                $table->text('roles')->nullable();
                $table->boolean('activo')->default(true);
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();
            });
        } else {
            Schema::table('cometidos_notificaciones_configuracion', function (Blueprint $table) {
                if (! Schema::hasColumn('cometidos_notificaciones_configuracion', 'categoria')) {
                    $table->string('categoria', 120)->nullable()->after('descripcion');
                }
                if (! Schema::hasColumn('cometidos_notificaciones_configuracion', 'tipo_destinatario')) {
                    $table->string('tipo_destinatario', 80)->default('rol_configurable')->after('categoria');
                }
                if (! Schema::hasColumn('cometidos_notificaciones_configuracion', 'roles')) {
                    $table->text('roles')->nullable()->after('correos');
                }
            });
        }

        CometidoNotificacionConfiguracion::sincronizarCatalogoProceso();
    }

    public function down(): void
    {
        if (Schema::hasTable('cometidos_notificaciones_configuracion')) {
            Schema::table('cometidos_notificaciones_configuracion', function (Blueprint $table) {
                if (Schema::hasColumn('cometidos_notificaciones_configuracion', 'roles')) {
                    $table->dropColumn('roles');
                }
                if (Schema::hasColumn('cometidos_notificaciones_configuracion', 'tipo_destinatario')) {
                    $table->dropColumn('tipo_destinatario');
                }
                if (Schema::hasColumn('cometidos_notificaciones_configuracion', 'categoria')) {
                    $table->dropColumn('categoria');
                }
            });
        }
    }
};
