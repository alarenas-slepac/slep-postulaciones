<?php

namespace Database\Seeders;

use App\Models\Curso;
use Illuminate\Database\Seeder;

class CursosEpjaEspecialSeeder extends Seeder
{
    /**
     * Crea o actualiza cursos base EPJA y Educación Especial/Laboral.
     *
     * Este seeder es idempotente: puede ejecutarse varias veces sin duplicar registros,
     * porque usa el campo codigo como clave lógica.
     */
    public function run(): void
    {
        $cursos = [
            [
                'codigo' => 'EPJA_BASICA_N1',
                'nombre' => 'Nivel Básico 1 EPJA (1° a 4° Básico)',
                'nivel_educativo' => 'EPJA Básica',
                'modalidad' => 'EPJA',
                'orden' => 190,
            ],
            [
                'codigo' => 'EPJA_BASICA_N2',
                'nombre' => 'Nivel Básico 2 EPJA (5° y 6° Básico)',
                'nivel_educativo' => 'EPJA Básica',
                'modalidad' => 'EPJA',
                'orden' => 200,
            ],
            [
                'codigo' => 'EPJA_BASICA_N3',
                'nombre' => 'Nivel Básico 3 EPJA (7° y 8° Básico)',
                'nivel_educativo' => 'EPJA Básica',
                'modalidad' => 'EPJA',
                'orden' => 210,
            ],
            [
                'codigo' => 'EPJA_MEDIA_N1',
                'nombre' => '1er Nivel Medio EPJA (1° y 2° Medio)',
                'nivel_educativo' => 'EPJA Media',
                'modalidad' => 'EPJA',
                'orden' => 220,
            ],
            [
                'codigo' => 'EPJA_MEDIA_N2',
                'nombre' => '2° Nivel Medio EPJA (3° y 4° Medio)',
                'nivel_educativo' => 'EPJA Media',
                'modalidad' => 'EPJA',
                'orden' => 230,
            ],
            [
                'codigo' => 'ESPECIAL_LABORAL_1',
                'nombre' => 'Laboral 1',
                'nivel_educativo' => 'Educación Especial',
                'modalidad' => 'Laboral',
                'orden' => 240,
            ],
            [
                'codigo' => 'ESPECIAL_LABORAL_2',
                'nombre' => 'Laboral 2',
                'nivel_educativo' => 'Educación Especial',
                'modalidad' => 'Laboral',
                'orden' => 250,
            ],
            [
                'codigo' => 'ESPECIAL_LABORAL_3',
                'nombre' => 'Laboral 3',
                'nivel_educativo' => 'Educación Especial',
                'modalidad' => 'Laboral',
                'orden' => 260,
            ],
        ];

        foreach ($cursos as $curso) {
            Curso::updateOrCreate(
                ['codigo' => $curso['codigo']],
                array_merge($curso, ['activo' => true])
            );
        }
    }
}
