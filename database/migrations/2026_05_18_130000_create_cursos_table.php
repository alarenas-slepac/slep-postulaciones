<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cursos')) {
            Schema::create('cursos', function (Blueprint $table) {
                $table->id();
                $table->string('nombre', 120)->unique();
                $table->string('codigo', 50)->unique();
                $table->string('nivel_educativo', 80);
                $table->string('modalidad', 80)->nullable();
                $table->unsignedSmallInteger('orden')->default(1)->index();
                $table->boolean('activo')->default(true)->index();
                $table->timestamps();
            });
        }

        $now = now();
        $cursos = [
            ['nombre' => 'NT1', 'codigo' => 'NT1', 'nivel_educativo' => 'Educación Parvularia', 'modalidad' => 'Parvularia', 'orden' => 10],
            ['nombre' => 'NT2', 'codigo' => 'NT2', 'nivel_educativo' => 'Educación Parvularia', 'modalidad' => 'Parvularia', 'orden' => 20],
            ['nombre' => '1° Básico', 'codigo' => '1B', 'nivel_educativo' => 'Educación Básica', 'modalidad' => 'Básica', 'orden' => 30],
            ['nombre' => '2° Básico', 'codigo' => '2B', 'nivel_educativo' => 'Educación Básica', 'modalidad' => 'Básica', 'orden' => 40],
            ['nombre' => '3° Básico', 'codigo' => '3B', 'nivel_educativo' => 'Educación Básica', 'modalidad' => 'Básica', 'orden' => 50],
            ['nombre' => '4° Básico', 'codigo' => '4B', 'nivel_educativo' => 'Educación Básica', 'modalidad' => 'Básica', 'orden' => 60],
            ['nombre' => '5° Básico', 'codigo' => '5B', 'nivel_educativo' => 'Educación Básica', 'modalidad' => 'Básica', 'orden' => 70],
            ['nombre' => '6° Básico', 'codigo' => '6B', 'nivel_educativo' => 'Educación Básica', 'modalidad' => 'Básica', 'orden' => 80],
            ['nombre' => '7° Básico', 'codigo' => '7B', 'nivel_educativo' => 'Educación Básica', 'modalidad' => 'Básica', 'orden' => 90],
            ['nombre' => '8° Básico', 'codigo' => '8B', 'nivel_educativo' => 'Educación Básica', 'modalidad' => 'Básica', 'orden' => 100],
            ['nombre' => '1° Medio', 'codigo' => '1M', 'nivel_educativo' => 'Educación Media', 'modalidad' => 'Común', 'orden' => 110],
            ['nombre' => '2° Medio', 'codigo' => '2M', 'nivel_educativo' => 'Educación Media', 'modalidad' => 'Común', 'orden' => 120],
            ['nombre' => '3° Medio HC', 'codigo' => '3M-HC', 'nivel_educativo' => 'Educación Media', 'modalidad' => 'Humanístico-Científica', 'orden' => 130],
            ['nombre' => '4° Medio HC', 'codigo' => '4M-HC', 'nivel_educativo' => 'Educación Media', 'modalidad' => 'Humanístico-Científica', 'orden' => 140],
            ['nombre' => '3° Medio TP', 'codigo' => '3M-TP', 'nivel_educativo' => 'Educación Media', 'modalidad' => 'Técnico-Profesional', 'orden' => 150],
            ['nombre' => '4° Medio TP', 'codigo' => '4M-TP', 'nivel_educativo' => 'Educación Media', 'modalidad' => 'Técnico-Profesional', 'orden' => 160],
            ['nombre' => '3° Medio Artístico', 'codigo' => '3M-ART', 'nivel_educativo' => 'Educación Media', 'modalidad' => 'Artística', 'orden' => 170],
            ['nombre' => '4° Medio Artístico', 'codigo' => '4M-ART', 'nivel_educativo' => 'Educación Media', 'modalidad' => 'Artística', 'orden' => 180],
        ];

        foreach ($cursos as $curso) {
            DB::table('cursos')->updateOrInsert(
                ['codigo' => $curso['codigo']],
                array_merge($curso, [
                    'activo' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cursos');
    }
};
