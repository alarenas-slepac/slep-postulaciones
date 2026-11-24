<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('postulant_job_profiles')) {
            Schema::create('postulant_job_profiles', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('postulant_profile_id');
                $table->unsignedBigInteger('area_desempeno_id')->nullable();
                $table->string('estamento', 20);
                $table->string('titulo', 200)->nullable();
                $table->string('nivel_estudios', 60);
                $table->string('mencion', 255)->nullable();
                $table->string('especialidad_tp', 150)->nullable();
                $table->string('cargos_funcion', 150)->nullable();
                $table->unsignedSmallInteger('anios_experiencia');
                $table->boolean('activo')->default(true);
                $table->timestamps();
            });
        }
        $this->addForeign('postulant_job_profiles', 'pjp_profile_fk', 'postulant_profile_id', 'postulant_profiles', 'cascade');
        $this->addForeign('postulant_job_profiles', 'pjp_area_fk', 'area_desempeno_id', 'areas_desempeno', 'set null');

        if (! Schema::hasColumn('user_documents', 'library_slot')) {
            Schema::table('user_documents', fn (Blueprint $table) => $table->string('library_slot', 48)->default(''));
        }
        if (! Schema::hasColumn('user_documents', 'credential_label')) {
            Schema::table('user_documents', fn (Blueprint $table) => $table->string('credential_label', 150)->nullable());
        }
        if (! Schema::hasColumn('user_documents', 'issued_on')) {
            Schema::table('user_documents', fn (Blueprint $table) => $table->date('issued_on')->nullable());
        }
        if (! Schema::hasIndex('user_documents', 'ud_library_slot_unique', 'unique')) {
            Schema::table('user_documents', fn (Blueprint $table) => $table->unique(['user_id', 'document_type_id', 'library_slot'], 'ud_library_slot_unique'));
        }
        foreach (Schema::getIndexes('user_documents') as $index) {
            if ($index['unique'] && $index['columns'] === ['user_id', 'document_type_id']) {
                Schema::table('user_documents', fn (Blueprint $table) => $table->dropUnique($index['name']));
            }
        }

        if (! Schema::hasColumn('bolsa_trabajo_ofertas', 'requisitos_documentales')) {
            Schema::table('bolsa_trabajo_ofertas', fn (Blueprint $table) => $table->json('requisitos_documentales')->nullable());
        }
        if (! Schema::hasColumn('bolsa_trabajo_postulaciones', 'cargo_presentado')) {
            Schema::table('bolsa_trabajo_postulaciones', fn (Blueprint $table) => $table->json('cargo_presentado')->nullable());
        }
        if (! Schema::hasTable('bolsa_trabajo_documentos_presentados')) {
            Schema::create('bolsa_trabajo_documentos_presentados', function (Blueprint $table) {
                $table->unsignedBigInteger('postulacion_id');
                $table->unsignedBigInteger('user_document_id');
            });
        }
        if (! Schema::hasIndex('bolsa_trabajo_documentos_presentados', 'btdp_post_doc_unique', 'unique')) {
            Schema::table('bolsa_trabajo_documentos_presentados', fn (Blueprint $table) => $table->unique(['postulacion_id', 'user_document_id'], 'btdp_post_doc_unique'));
        }
        if (! Schema::hasIndex('bolsa_trabajo_documentos_presentados', 'btdp_doc_idx')) {
            Schema::table('bolsa_trabajo_documentos_presentados', fn (Blueprint $table) => $table->index('user_document_id', 'btdp_doc_idx'));
        }
        $this->addForeign('bolsa_trabajo_documentos_presentados', 'btdp_post_fk', 'postulacion_id', 'bolsa_trabajo_postulaciones', 'cascade');
        $this->addForeign('bolsa_trabajo_documentos_presentados', 'btdp_doc_fk', 'user_document_id', 'user_documents', 'cascade');
    }

    private function addForeign(string $table, string $name, string $column, string $reference, string $onDelete): void
    {
        if (DB::getDriverName() === 'sqlite') {
            // SQLite agrega la restricción reconstruyendo la tabla. Detectar la
            // columna porque PRAGMA no devuelve el nombre de la restricción.
            $exists = collect(Schema::getForeignKeys($table))->contains(fn (array $key) => $key['columns'] === [$column]);
        } else {
            $exists = collect(Schema::getForeignKeys($table))->contains('name', $name);
        }

        if (! $exists) {
            Schema::table($table, fn (Blueprint $blueprint) => $blueprint->foreign($column, $name)->references('id')->on($reference)->onDelete($onDelete));
        }
    }

    public function down(): void
    {
        throw new RuntimeException('Revertir esta migración requiere conservar los perfiles y documentos presentados.');
    }
};
