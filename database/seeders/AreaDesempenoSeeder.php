<?php

namespace Database\Seeders;

use App\Models\AreaDesempeno;
use Illuminate\Database\Seeder;

class AreaDesempenoSeeder extends Seeder
{
    public function run(): void
    {
        $docentes = [
            'Educadora de Párvulos',
            'Docente General Básica',
            'Educador(a) Diferencial',
            'Docente Técnico Profesional',
            'Lenguaje y Comunicación',
            'Matemática',
            'Historia',
            'Ciencias Naturales',
            'Biología',
            'Lengua Indígena o Lengua y Cultura de los Pueblos Originarios Ancestrales',
            'Química',
            'Física',
            'Inglés',
            'Educación Física y Salud',
            'Artes Visuales',
            'Música',
            'Tecnología',
            'Filosofía',
            'Religión Católica',
            'Religión Evangélica',
            'UTP',
            'Encargado(a) de Convivencia Escolar',
            'Coordinador(a) PIE',
            'Inspector(a) General',
            'Evaluador(a)',
        ];

        // Propuesta inicial AAEE (ajustable a tu realidad)
        $asistentes = [
            'Asistente de Aula',
            'Inspector(a) Educacional',
            'Asistente Diferencial',
            'Asistente de Párvulos',
            'Soporte Computacional',
            'Psicólogo(a)',
            'Trabajador(a) Social',
            'Fonoaudiólogo(a)',
            'Terapeuta Ocupacional',
            'Administrativo(a)',
            'Monitor(a) Deportivo',
            'Celador',
            'Auxiliar de Servicios',
            'Encargado(a) CRA / Biblioteca',
            'Encargado(a) de Convivencia Escolar (AAEE)',
        ];

        foreach ($docentes as $nombre) {
            AreaDesempeno::updateOrCreate(
                ['estamento' => 'docente', 'nombre' => $nombre],
                ['activo' => true]
            );
        }

        foreach ($asistentes as $nombre) {
            AreaDesempeno::updateOrCreate(
                ['estamento' => 'asistente', 'nombre' => $nombre],
                ['activo' => true]
            );
        }
    }
}
