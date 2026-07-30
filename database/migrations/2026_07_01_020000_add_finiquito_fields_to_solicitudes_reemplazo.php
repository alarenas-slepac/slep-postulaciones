<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('solicitudes_reemplazo', function (Blueprint $table) {
            if (!Schema::hasColumn('solicitudes_reemplazo', 'finiquito_pagado')) {
                $table->boolean('finiquito_pagado')->default(false)->after('cerrado_at');
            }
            if (!Schema::hasColumn('solicitudes_reemplazo', 'finiquito_pagado_por_user_id')) {
                $table->foreignId('finiquito_pagado_por_user_id')
                    ->nullable()
                    ->after('finiquito_pagado')
                    ->constrained('users')
                    ->nullOnDelete();
            }
            if (!Schema::hasColumn('solicitudes_reemplazo', 'finiquito_pagado_at')) {
                $table->timestamp('finiquito_pagado_at')->nullable()->after('finiquito_pagado_por_user_id');
            }
            if (!Schema::hasColumn('solicitudes_reemplazo', 'finiquito_observacion')) {
                $table->text('finiquito_observacion')->nullable()->after('finiquito_pagado_at');
            }

            $table->index(['finiquito_pagado', 'fecha_termino'], 'sr_finiquito_pagado_termino_idx');
        });
    }

    public function down(): void
    {
        Schema::table('solicitudes_reemplazo', function (Blueprint $table) {
            if (Schema::hasColumn('solicitudes_reemplazo', 'finiquito_pagado')) {
                $table->dropIndex('sr_finiquito_pagado_termino_idx');
            }
            if (Schema::hasColumn('solicitudes_reemplazo', 'finiquito_observacion')) {
                $table->dropColumn('finiquito_observacion');
            }
            if (Schema::hasColumn('solicitudes_reemplazo', 'finiquito_pagado_at')) {
                $table->dropColumn('finiquito_pagado_at');
            }
            if (Schema::hasColumn('solicitudes_reemplazo', 'finiquito_pagado_por_user_id')) {
                $table->dropConstrainedForeignId('finiquito_pagado_por_user_id');
            }
            if (Schema::hasColumn('solicitudes_reemplazo', 'finiquito_pagado')) {
                $table->dropColumn('finiquito_pagado');
            }
        });
    }
};
