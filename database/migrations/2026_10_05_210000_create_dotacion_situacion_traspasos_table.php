<?php

use App\Support\DotacionSituacionesAnuales;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('dotacion_situacion_traspasos')) {
            Schema::create('dotacion_situacion_traspasos', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('establecimiento_id');
                $table->unsignedSmallInteger('anio_origen');
                $table->unsignedSmallInteger('anio_destino');
                $table->string('docente_rut_normalizado', 20);
                $table->unsignedBigInteger('situacion_origen_id');
                $table->boolean('copiada');
                $table->timestamps();
                $table->unique(['establecimiento_id', 'anio_destino', 'docente_rut_normalizado'], 'dst_est_anio_rut_uk');
                // El historial sobrevive a la eliminación de la situación
                // destino: no se vuelve a copiar una decisión eliminada.
            });
        }

        if (Schema::hasTable('dotacion_docente_exclusiones') && Schema::hasTable('establecimientos')) {
            // Completa el proceso 2027 sin rellenar años históricos anteriores.
            DB::table('dotacion_docente_exclusiones')->where('anio', 2026)
                ->select('establecimiento_id')->distinct()->orderBy('establecimiento_id')
                ->get()->each(fn ($row) => app(DotacionSituacionesAnuales::class)
                    ->copiarAlAnioSiguiente((int) $row->establecimiento_id, 2026));
        }
    }

    public function down(): void
    {
        // Se conservan situaciones e historial: no se puede distinguir qué
        // registros destino fueron revisados posteriormente por sus usuarios.
    }
};
