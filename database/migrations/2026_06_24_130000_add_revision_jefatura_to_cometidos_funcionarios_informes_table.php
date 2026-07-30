<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cometidos_funcionarios_informes')) {
            return;
        }

        Schema::table('cometidos_funcionarios_informes', function (Blueprint $table) {
            if (! Schema::hasColumn('cometidos_funcionarios_informes', 'fecha_revision_jefatura')) {
                $table->timestamp('fecha_revision_jefatura')->nullable()->after('fecha_envio');
            }
            if (! Schema::hasColumn('cometidos_funcionarios_informes', 'jefatura_revisora_id')) {
                $table->unsignedBigInteger('jefatura_revisora_id')->nullable()->after('fecha_revision_jefatura');
            }
            if (! Schema::hasColumn('cometidos_funcionarios_informes', 'decision_jefatura')) {
                $table->string('decision_jefatura', 40)->nullable()->after('jefatura_revisora_id');
            }
            if (! Schema::hasColumn('cometidos_funcionarios_informes', 'observacion_jefatura')) {
                $table->text('observacion_jefatura')->nullable()->after('decision_jefatura');
            }
        });

        Schema::table('cometidos_funcionarios_informes', function (Blueprint $table) {
            if (Schema::hasColumn('cometidos_funcionarios_informes', 'jefatura_revisora_id')) {
                $table->foreign('jefatura_revisora_id', 'com_inf_jef_fk')
                    ->references('id')->on('users')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('cometidos_funcionarios_informes')) {
            return;
        }

        Schema::table('cometidos_funcionarios_informes', function (Blueprint $table) {
            if (Schema::hasColumn('cometidos_funcionarios_informes', 'jefatura_revisora_id')) {
                $table->dropForeign('com_inf_jef_fk');
            }
        });

        Schema::table('cometidos_funcionarios_informes', function (Blueprint $table) {
            foreach (['observacion_jefatura', 'decision_jefatura', 'jefatura_revisora_id', 'fecha_revision_jefatura'] as $column) {
                if (Schema::hasColumn('cometidos_funcionarios_informes', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
