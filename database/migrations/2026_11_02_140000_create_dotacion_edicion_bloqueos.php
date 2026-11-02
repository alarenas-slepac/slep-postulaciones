<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dotacion_edicion_bloqueos', function (Blueprint $table) {
            $table->id();
            $table->string('clave', 80)->unique();
            $table->foreignId('establecimiento_id')->nullable()->constrained('establecimientos')->nullOnDelete();
            $table->boolean('bloqueado')->default(false);
            $table->unsignedInteger('version')->default(0);
            $table->text('motivo')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('rol_actualizador', 80)->nullable();
            $table->timestamps();
        });
        Schema::create('dotacion_edicion_bloqueo_historial', function (Blueprint $table) {
            $table->id();
            $table->string('clave', 80)->index();
            $table->boolean('estado_anterior');
            $table->boolean('estado_nuevo');
            $table->unsignedInteger('version');
            $table->text('motivo')->nullable();
            $table->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('rol_activo', 80);
            $table->timestamp('created_at');
        });
        DB::table('dotacion_edicion_bloqueos')->insert([
            'clave' => 'general', 'bloqueado' => false, 'version' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('dotacion_edicion_bloqueo_historial');
        Schema::dropIfExists('dotacion_edicion_bloqueos');
    }
};
