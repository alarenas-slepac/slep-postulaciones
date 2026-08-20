<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('descuentos_cgr_documentos_mensuales')) {
            return;
        }

        Schema::create('descuentos_cgr_documentos_mensuales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('descuento_cgr_id')->constrained('descuentos_cgr')->cascadeOnDelete();
            $table->unsignedSmallInteger('numero_cuota');
            $table->date('periodo');
            $table->string('codigo_verificacion', 40)->unique('dcgr_doc_mensual_codigo_unique');
            $table->char('documento_hash', 64);
            $table->timestamp('documento_emitido_en');
            $table->timestamps();

            $table->unique(
                ['descuento_cgr_id', 'numero_cuota'],
                'dcgr_doc_mensual_descuento_cuota_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('descuentos_cgr_documentos_mensuales');
    }
};
