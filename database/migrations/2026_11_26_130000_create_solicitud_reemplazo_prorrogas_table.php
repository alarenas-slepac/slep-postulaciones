<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLA = 'solicitud_reemplazo_prorrogas';

    public function up(): void
    {
        $sqlite = Schema::getConnection()->getDriverName() === 'sqlite';
        if (! Schema::hasTable(self::TABLA)) {
            Schema::create(self::TABLA, function (Blueprint $table) use ($sqlite): void {
                $table->id();
                $table->unsignedSmallInteger('anio_diciembre');
                $table->string('estamento', 12);
                $table->string('rut', 12);
                $table->date('vigencia_desde');
                $table->date('vigencia_hasta');
                $table->json('antecedentes');
                $table->string('numero_resolucion', 100);
                $table->date('fecha_resolucion');
                $table->string('resolucion_path');
                $table->string('resolucion_nombre');
                $table->text('observacion')->nullable();
                $table->foreignId('registrada_por_id')->nullable();
                $table->timestamps();

                if ($sqlite) {
                    $table->foreign('registrada_por_id', 'srp_usuario_fk')->references('id')->on('users')->nullOnDelete();
                }
            });
        }

        if (! Schema::hasIndex(self::TABLA, 'srp_periodo_est_rut_uq')) {
            Schema::table(self::TABLA, fn (Blueprint $table) => $table
                ->unique(['anio_diciembre', 'estamento', 'rut'], 'srp_periodo_est_rut_uq'));
        }

        if (! $sqlite && ! in_array('srp_usuario_fk', array_column(Schema::getForeignKeys(self::TABLA), 'name'), true)) {
            Schema::table(self::TABLA, fn (Blueprint $table) => $table
                ->foreign('registrada_por_id', 'srp_usuario_fk')->references('id')->on('users')->nullOnDelete());
        }
    }

    public function down(): void
    {
        Schema::dropIfExists(self::TABLA);
    }
};
