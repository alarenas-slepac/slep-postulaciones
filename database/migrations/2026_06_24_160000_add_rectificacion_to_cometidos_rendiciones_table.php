<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cometidos_funcionarios_rendiciones')) {
            return;
        }

        Schema::table('cometidos_funcionarios_rendiciones', function (Blueprint $table) {
            if (! Schema::hasColumn('cometidos_funcionarios_rendiciones', 'rectificacion_count')) {
                $table->unsignedInteger('rectificacion_count')->default(0)->after('estado');
            }
            if (! Schema::hasColumn('cometidos_funcionarios_rendiciones', 'fecha_ultima_rectificacion')) {
                $table->timestamp('fecha_ultima_rectificacion')->nullable()->after('fecha_envio_rendicion');
            }
            if (! Schema::hasColumn('cometidos_funcionarios_rendiciones', 'observacion_rectificacion')) {
                $table->text('observacion_rectificacion')->nullable()->after('observacion_establecimiento');
            }
            if (! Schema::hasColumn('cometidos_funcionarios_rendiciones', 'rectificado_por')) {
                $table->unsignedBigInteger('rectificado_por')->nullable()->after('created_by');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('cometidos_funcionarios_rendiciones')) {
            return;
        }

        Schema::table('cometidos_funcionarios_rendiciones', function (Blueprint $table) {
            $columns = [
                'rectificacion_count',
                'fecha_ultima_rectificacion',
                'observacion_rectificacion',
                'rectificado_por',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('cometidos_funcionarios_rendiciones', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
