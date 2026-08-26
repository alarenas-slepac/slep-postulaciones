<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('licencias_medicas')) {
            Schema::table('licencias_medicas', function (Blueprint $table) {
                if (! Schema::hasColumn('licencias_medicas', 'estado_administrativo_codigo')) {
                    $table->string('estado_administrativo_codigo', 50)->nullable()->index()->after('estado_actual');
                }
                if (! Schema::hasColumn('licencias_medicas', 'estado_compin_codigo')) {
                    $table->string('estado_compin_codigo', 50)->nullable()->index()->after('estado_compin');
                }
                if (! Schema::hasColumn('licencias_medicas', 'estado_recuperacion_codigo')) {
                    $table->string('estado_recuperacion_codigo', 50)->nullable()->index()->after('estado_compin_codigo');
                }
            });

            $this->normalizarEstadosExistentes();
        }

        if (Schema::hasTable('licencias_medicas_historial')) {
            Schema::table('licencias_medicas_historial', function (Blueprint $table) {
                if (! Schema::hasColumn('licencias_medicas_historial', 'estado_dimension')) {
                    $table->string('estado_dimension', 30)->nullable()->index()->after('descripcion');
                }
                if (! Schema::hasColumn('licencias_medicas_historial', 'estado_anterior')) {
                    $table->string('estado_anterior', 80)->nullable()->after('estado_dimension');
                }
                if (! Schema::hasColumn('licencias_medicas_historial', 'estado_nuevo')) {
                    $table->string('estado_nuevo', 80)->nullable()->after('estado_anterior');
                }
                if (! Schema::hasColumn('licencias_medicas_historial', 'origen')) {
                    $table->string('origen', 40)->nullable()->index()->after('datos_nuevos');
                }
                if (! Schema::hasColumn('licencias_medicas_historial', 'importacion_id')) {
                    $table->unsignedBigInteger('importacion_id')->nullable()->index()->after('origen');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('licencias_medicas_historial')) {
            Schema::table('licencias_medicas_historial', function (Blueprint $table) {
                foreach (['importacion_id', 'origen', 'estado_nuevo', 'estado_anterior', 'estado_dimension'] as $column) {
                    if (Schema::hasColumn('licencias_medicas_historial', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('licencias_medicas')) {
            Schema::table('licencias_medicas', function (Blueprint $table) {
                foreach (['estado_recuperacion_codigo', 'estado_compin_codigo', 'estado_administrativo_codigo'] as $column) {
                    if (Schema::hasColumn('licencias_medicas', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }

    private function normalizarEstadosExistentes(): void
    {
        foreach ((array) config('licencias_medicas.aliases.administrativo', []) as $codigo => $aliases) {
            DB::table('licencias_medicas')
                ->whereNull('estado_administrativo_codigo')
                ->whereIn(DB::raw('UPPER(TRIM(estado_actual))'), array_map('mb_strtoupper', (array) $aliases))
                ->update(['estado_administrativo_codigo' => $codigo]);
        }

        DB::table('licencias_medicas')
            ->whereNull('estado_administrativo_codigo')
            ->update(['estado_administrativo_codigo' => 'otro']);

        foreach ((array) config('licencias_medicas.aliases.compin', []) as $codigo => $aliases) {
            DB::table('licencias_medicas')
                ->whereNull('estado_compin_codigo')
                ->whereIn(DB::raw("UPPER(TRIM(COALESCE(NULLIF(estado_compin, ''), primer_estado)))"), array_map('mb_strtoupper', (array) $aliases))
                ->update(['estado_compin_codigo' => $codigo]);
        }

        DB::table('licencias_medicas')
            ->whereNull('estado_compin_codigo')
            ->where(function ($query) {
                $query->whereNotNull('estado_compin')
                    ->where('estado_compin', '<>', '')
                    ->orWhereNotNull('primer_estado');
            })
            ->update(['estado_compin_codigo' => 'otro']);

        DB::table('licencias_medicas')
            ->whereNull('estado_compin_codigo')
            ->update(['estado_compin_codigo' => 'sin_informacion']);

        DB::table('licencias_medicas')
            ->whereNull('estado_recuperacion_codigo')
            ->whereRaw("UPPER(TRIM(COALESCE(se_puede_recuperar, ''))) LIKE 'NO%'")
            ->update(['estado_recuperacion_codigo' => 'no_recuperable']);

        DB::table('licencias_medicas')
            ->whereNull('estado_recuperacion_codigo')
            ->whereNotNull('gestion_cobro')
            ->where('gestion_cobro', '<>', '')
            ->update(['estado_recuperacion_codigo' => 'en_cobro']);

        DB::table('licencias_medicas')
            ->whereNull('estado_recuperacion_codigo')
            ->update(['estado_recuperacion_codigo' => 'no_evaluada']);
    }
};
