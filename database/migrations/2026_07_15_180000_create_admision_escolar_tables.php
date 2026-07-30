<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('admision_establecimientos')) {
            Schema::create('admision_establecimientos', function (Blueprint $table) {
                $table->id();
                $table->foreignId('establecimiento_id')
                    ->unique()
                    ->constrained('establecimientos')
                    ->cascadeOnDelete();

                $table->string('slug', 255)->unique();
                $table->string('estado', 20)->default('borrador')->index();
                $table->boolean('destacado')->default(false)->index();
                $table->unsignedSmallInteger('orden')->default(0)->index();

                $table->text('sello_educativo')->nullable();
                $table->text('descripcion_corta')->nullable();
                $table->string('director_nombre', 180)->nullable();
                $table->text('director_resena')->nullable();
                $table->string('director_foto_path', 500)->nullable();
                $table->string('logo_path', 500)->nullable();

                $table->string('sitio_web_url', 500)->nullable();
                $table->string('facebook_url', 500)->nullable();
                $table->string('instagram_url', 500)->nullable();
                $table->string('direccion_publica', 500)->nullable();
                $table->string('sector', 20)->nullable();
                $table->string('telefono_publico', 80)->nullable();
                $table->string('email_publico', 255)->nullable();

                $table->timestamp('publicado_at')->nullable()->index();
                $table->foreignId('publicado_por')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('actualizado_por')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['estado', 'destacado', 'orden'], 'admision_publicacion_orden_idx');
            });
        }

        if (! Schema::hasTable('admision_establecimiento_imagenes')) {
            Schema::create('admision_establecimiento_imagenes', function (Blueprint $table) {
                $table->id();
                // Nombre explícito para no superar el límite de 64 caracteres de MySQL.
                $table->unsignedBigInteger('admision_establecimiento_id');
                $table->foreign('admision_establecimiento_id', 'admision_imagenes_perfil_fk')
                    ->references('id')
                    ->on('admision_establecimientos')
                    ->cascadeOnDelete();
                $table->string('imagen_path', 500);
                $table->string('original_name', 255)->nullable();
                $table->string('mime_type', 120)->nullable();
                $table->unsignedBigInteger('tamano_bytes')->nullable();
                $table->string('texto_alternativo', 255);
                $table->string('titulo', 255)->nullable();
                $table->text('descripcion')->nullable();
                $table->boolean('es_portada')->default(false)->index();
                $table->unsignedSmallInteger('orden')->default(0)->index();
                $table->timestamps();

                $table->index(
                    ['admision_establecimiento_id', 'orden'],
                    'admision_imagenes_perfil_orden_idx'
                );
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('admision_establecimiento_imagenes');
        Schema::dropIfExists('admision_establecimientos');
    }
};
