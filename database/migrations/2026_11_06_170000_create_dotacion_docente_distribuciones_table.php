<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('dotacion_docente_distribuciones')) {
            return;
        }
        Schema::create('dotacion_docente_distribuciones', function (Blueprint $table): void {
            $table->id();
            $table->uuid('solicitud_uuid')->unique('ddd_solicitud_uk');
            $table->unsignedSmallInteger('anio');
            $table->foreignId('origen_id')->constrained('establecimientos', indexName: 'ddd_origen_fk');
            $table->foreignId('destino_id')->constrained('establecimientos', indexName: 'ddd_destino_fk');
            $table->string('docente_rut_normalizado', 32);
            $table->string('bloque', 24);
            $table->string('tipo_horas', 16);
            $table->decimal('horas', 8, 2);
            $table->json('antecedente');
            $table->text('observacion')->nullable();
            $table->foreignId('created_by')->nullable();
            $table->foreignId('anulada_by')->nullable();
            $table->timestamp('anulada_at')->nullable();
            $table->text('motivo_anulacion')->nullable();
            $table->unsignedBigInteger('reemplaza_id')->nullable();
            $table->timestamps();
            $table->index(['origen_id', 'anio', 'docente_rut_normalizado', 'bloque', 'tipo_horas'], 'ddd_saldo_idx');
            $table->index(['anio', 'destino_id'], 'ddd_destino_idx');
        });
    }

    public function down(): void
    {
        // Conserva las distribuciones y su historial al revertir el código.
    }
};
