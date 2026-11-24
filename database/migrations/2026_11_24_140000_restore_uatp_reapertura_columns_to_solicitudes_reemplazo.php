<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLA = 'solicitudes_reemplazo';

    private const USUARIO = 'uatp_reapertura_user_id';

    public function up(): void
    {
        if (! Schema::hasTable(self::TABLA)) {
            throw new RuntimeException('La reparación requiere la tabla '.self::TABLA.'.');
        }

        $columnas = Schema::getColumnListing(self::TABLA);
        Schema::table(self::TABLA, function (Blueprint $table) use ($columnas): void {
            if (! in_array('uatp_rechazo_reabierto_motivo', $columnas, true)) {
                $table->text('uatp_rechazo_reabierto_motivo')->nullable();
            }
            if (! in_array('uatp_reapertura_motivo', $columnas, true)) {
                $table->text('uatp_reapertura_motivo')->nullable();
            }
            if (! in_array(self::USUARIO, $columnas, true)) {
                $table->unsignedBigInteger(self::USUARIO)->nullable();
            }
            if (! in_array('uatp_reapertura_at', $columnas, true)) {
                $table->timestamp('uatp_reapertura_at')->nullable();
            }
        });

        // MySQL puede confirmar las columnas antes de crear el índice o la clave foránea.
        $indexado = collect(Schema::getIndexes(self::TABLA))
            ->contains(fn (array $indice) => ($indice['columns'][0] ?? null) === self::USUARIO);
        if (! $indexado) {
            Schema::table(self::TABLA, fn (Blueprint $table) => $table->index(self::USUARIO, 'sr_uatp_reapertura_usuario_idx'));
        }

        $tieneClave = collect(Schema::getForeignKeys(self::TABLA))
            ->contains(fn (array $clave) => $clave['columns'] === [self::USUARIO]);
        if (! $tieneClave) {
            Schema::table(self::TABLA, function (Blueprint $table): void {
                $table->foreign(self::USUARIO, 'sr_uatp_reapertura_usuario_fk')
                    ->references('id')->on('users')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        // Los campos pueden preexistir y registrar reaperturas históricas.
    }
};
