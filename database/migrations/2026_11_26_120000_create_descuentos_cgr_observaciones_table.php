<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLA = 'descuentos_cgr_observaciones';

    private const REFERENCIAS = [
        'cgr_obs_desc_fk' => ['descuento_cgr_id', 'descuentos_cgr', 'cascade'],
        'cgr_obs_autor_fk' => ['observada_por_id', 'users', 'set null'],
        'cgr_obs_reenvio_fk' => ['reenviada_por_id', 'users', 'set null'],
    ];

    public function up(): void
    {
        $sqlite = Schema::getConnection()->getDriverName() === 'sqlite';
        if (! Schema::hasTable(self::TABLA)) {
            Schema::create(self::TABLA, function (Blueprint $table) use ($sqlite): void {
                $table->id();
                $table->foreignId('descuento_cgr_id');
                $table->foreignId('observada_por_id')->nullable();
                $table->foreignId('reenviada_por_id')->nullable();
                $table->string('etapa_destino', 32);
                $table->text('observacion');
                $table->json('elementos');
                $table->json('respaldos_previos')->nullable();
                $table->timestamp('reenviada_en')->nullable();
                $table->timestamps();

                if ($sqlite) {
                    foreach (self::REFERENCIAS as $nombre => [$columna, $tabla, $alEliminar]) {
                        $table->foreign($columna, $nombre)->references('id')->on($tabla)->onDelete($alEliminar);
                    }
                }
            });
        }

        if (! Schema::hasIndex(self::TABLA, 'cgr_obs_pend_idx')) {
            Schema::table(self::TABLA, fn (Blueprint $table) => $table->index(['descuento_cgr_id', 'reenviada_en'], 'cgr_obs_pend_idx'));
        }

        if (! $sqlite) {
            $existentes = array_column(Schema::getForeignKeys(self::TABLA), 'name');
            foreach (self::REFERENCIAS as $nombre => [$columna, $tabla, $alEliminar]) {
                if (! in_array($nombre, $existentes, true)) {
                    Schema::table(self::TABLA, fn (Blueprint $table) => $table->foreign($columna, $nombre)
                        ->references('id')->on($tabla)->onDelete($alEliminar));
                }
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists(self::TABLA);
    }
};
