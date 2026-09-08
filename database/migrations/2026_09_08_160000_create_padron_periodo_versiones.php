<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Solo estructura: nunca copiar datos personales durante el despliegue.
        Schema::create('padron_periodo_versiones', function (Blueprint $t): void {
            $t->id();
            $t->unsignedInteger('periodo')->index();
            $t->foreignId('padron_revision_id')->constrained('padron_revisiones');
            $t->string('origen', 20);
            $t->unsignedBigInteger('usuario_id');
            $t->unsignedInteger('registros')->default(0);
            $t->string('huella', 64)->nullable();
            $t->timestamp('created_at');
            $t->timestamp('completada_at')->nullable();
            $t->unique(['padron_revision_id', 'periodo', 'origen'], 'padron_version_revision_unique');
        });
        Schema::create('padron_periodo_personal', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('version_id')->constrained('padron_periodo_versiones');
            // Es el ID contractual original, no el ID de esta copia.
            $t->foreignId('personal_id')->constrained('reemplazos_personal');
            $t->unsignedBigInteger('establecimiento_id')->nullable();
            $t->unsignedInteger('rbd')->nullable();
            foreach (['rut', 'nombre', 'tipocontrato', 'financiamiento', 'estatuto', 'escalafon', 'tramo', 'row_hash', 'source_filename'] as $campo) {
                $t->text($campo)->nullable();
            }
            foreach (['fecha_nacimiento', 'fecha_ingreso', 'fecha_antiguedad', 'fecha_termino'] as $campo) {
                $t->date($campo)->nullable();
            }
            foreach (['anio', 'mes', 'jornada', 'jornada_basica', 'jornada_media', 'bienios'] as $campo) {
                $t->integer($campo)->nullable();
            }
            $t->boolean('vigente')->default(true);
            $t->unsignedBigInteger('created_by')->nullable();
            $t->timestamps();
            $t->json('datos');
            $t->unique(['version_id', 'personal_id']);
            $t->index(['version_id', 'establecimiento_id']);
        });
    }

    public function down(): void
    {
        throw new RuntimeException('El historial del padrón requiere una reversión específica autorizada, sin eliminar versiones.');
    }
};
