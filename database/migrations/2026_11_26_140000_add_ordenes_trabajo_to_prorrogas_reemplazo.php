<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('orden_trabajo_correlativos')) {
            Schema::create('orden_trabajo_correlativos', function (Blueprint $table): void {
                $table->id();
                $table->string('origen', 16);
                $table->unsignedBigInteger('origen_id');
                $table->timestamp('created_at')->nullable();
            });
        }
        if (! Schema::hasIndex('orden_trabajo_correlativos', 'otc_origen_uq')) {
            Schema::table('orden_trabajo_correlativos', fn (Blueprint $table) => $table
                ->unique(['origen', 'origen_id'], 'otc_origen_uq'));
        }
        if (! DB::table('orden_trabajo_correlativos')->exists()) {
            $ultimo = 0;
            $hayModificaciones = Schema::hasTable('solicitudes_reemplazo_modificaciones_termino');
            $emitidas = DB::table('solicitudes_reemplazo')->where(function ($query) use ($hayModificaciones): void {
                $query->whereNotNull('orden_trabajo_pdf_path')->orWhereNotNull('orden_trabajo_creada_at');
                if ($hayModificaciones) {
                    $query->orWhereIn('id', DB::table('solicitudes_reemplazo_modificaciones_termino')
                        ->select('solicitud_reemplazo_id')->whereNotNull('orden_trabajo_anterior_path'));
                }
            });
            foreach ($emitidas->select('id', 'correlativo', 'numero_solicitud')->lazyById(500) as $solicitud) {
                $ultimo = max($ultimo, (int) ($solicitud->numero_solicitud ?? $solicitud->correlativo ?? $solicitud->id));
            }
            if ($ultimo > 0) {
                DB::table('orden_trabajo_correlativos')->insertOrIgnore([
                    'id' => $ultimo, 'origen' => 'historico', 'origen_id' => 0, 'created_at' => now(),
                ]);
            }
        }
        foreach (['orden_trabajo_pdf_path' => 'string', 'orden_trabajo_emitida_por_id' => 'unsignedBigInteger', 'orden_trabajo_emitida_at' => 'timestamp'] as $columna => $tipo) {
            if (! Schema::hasColumn('solicitud_reemplazo_prorrogas', $columna)) {
                Schema::table('solicitud_reemplazo_prorrogas', fn (Blueprint $table) => $table->{$tipo}($columna)->nullable());
            }
        }
    }

    public function down(): void
    {
        Schema::table('solicitud_reemplazo_prorrogas', fn (Blueprint $table) => $table->dropColumn([
            'orden_trabajo_pdf_path', 'orden_trabajo_emitida_por_id', 'orden_trabajo_emitida_at',
        ]));
        Schema::dropIfExists('orden_trabajo_correlativos');
    }
};
