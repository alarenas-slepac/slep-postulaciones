<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tramites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('tipo', 120);
            $table->string('estado', 50)->default('borrador');
            $table->string('rut_snapshot', 20)->nullable();
            $table->string('nombre_completo_snapshot', 255)->nullable();
            $table->string('email_snapshot', 190)->nullable();
            $table->string('estatuto_snapshot', 255)->nullable();
            $table->string('escalafon_snapshot', 255)->nullable();
            $table->unsignedBigInteger('establecimiento_id_snapshot')->nullable();
            $table->string('establecimiento_nombre_snapshot', 255)->nullable();
            $table->timestamp('enviado_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'tipo']);
            $table->index(['estado', 'enviado_at']);
            $table->foreign('establecimiento_id_snapshot')->references('id')->on('establecimientos')->nullOnDelete();
        });

        Schema::create('tramite_documentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tramite_id')->constrained('tramites')->cascadeOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('tipo_documento', 120);
            $table->string('formato', 20)->default('pdf');
            $table->string('path', 500);
            $table->string('original_name', 255);
            $table->string('mime', 120)->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->date('fecha_inicio')->nullable();
            $table->date('fecha_termino')->nullable();
            $table->timestamps();

            $table->index(['tramite_id', 'tipo_documento']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tramite_documentos');
        Schema::dropIfExists('tramites');
    }
};
