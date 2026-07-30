<?php

namespace Database\Seeders;

use App\Models\DocumentType;
use Illuminate\Database\Seeder;

class DocumentTypeSeeder extends Seeder
{
    public function run(): void
    {
        $catalog = [
            // Ambos
            ['slug' => 'curriculum', 'label' => 'Currículum Vitae', 'for' => 'both'],
            ['slug' => 'antecedentes_especiales', 'label' => 'Antecedentes especiales', 'for' => 'both'],
            ['slug' => 'nacimiento', 'label' => 'Certificado de Nacimiento', 'for' => 'both'],
            ['slug' => 'cedula', 'label' => 'Cédula de Identidad (ambos lados)', 'for' => 'both'],
            ['slug' => 'licencia_media', 'label' => 'Licencia de Enseñanza Media', 'for' => 'both'],
            ['slug' => 'afp_afiliacion', 'label' => 'AFP: Certificado de Afiliación', 'for' => 'both'],
            ['slug' => 'afp_cotizaciones', 'label' => 'AFP: Certificado de Cotizaciones', 'for' => 'both'],
            ['slug' => 'salud_afiliacion', 'label' => 'Salud: Certificado de Afiliación (FONASA/ISAPRE)', 'for' => 'both'],
            ['slug' => 'cert_medico', 'label' => 'Certificado Médico', 'for' => 'both'],
            ['slug' => 'inhabilidades_menores', 'label' => 'Certificado Inhabilidades para trabajar con menores', 'for' => 'both'],
            ['slug' => 'inhabilidad_maltrato', 'label' => 'Certificado Inhabilidad por maltrato relevante', 'for' => 'both'],
            ['slug' => 'declaracion_cargo_publico', 'label' => 'Declaración jurada para ejercer cargo público', 'for' => 'both', 'template' => 'templates/declaracion_cargo_publico.pdf'],
            ['slug' => 'ficha_antecedentes', 'label' => 'Ficha de antecedentes', 'for' => 'both', 'template' => 'templates/ficha_antecedentes.pdf'],

            // Docentes
            ['slug' => 'titulo', 'label' => 'Título profesional o técnico', 'for' => 'both'], // también útil para asistentes con TNS
            ['slug' => 'titulo_mencion', 'label' => 'Título con mención', 'for' => 'docente', 'cond' => ['require_mencion' => true]],
            ['slug' => 'cert_semestres_horas', 'label' => 'Certificado de semestres/horas', 'for' => 'docente'],
            ['slug' => 'tramo_docente', 'label' => 'Certificado de tramo docente', 'for' => 'docente'],

            // Condicionales
            ['slug' => 'situacion_militar', 'label' => 'Situación militar al día', 'for' => 'both', 'cond' => ['require_genero_in' => ['masculino']]],
            ['slug' => 'idoneidad_religion', 'label' => 'Idoneidad para Religión', 'for' => 'docente', 'cond' => ['require_area_in' => ['Religión Católica', 'Religión Evangélica']]],
            // Registro MINEDUC para Diferencial (docente) o cargos asistente Psic/Fono
            ['slug' => 'registro_mineduc', 'label' => 'Registro MINEDUC (Dif./Psic./Fono)', 'for' => 'conditional', 'cond' => [
                'require_area_in' => ['Educador(a) Diferencial', 'Educadora Diferencial'],
                'or_require_cargo_in' => ['Psicólogo(a)', 'Fonoaudiólogo(a)'] // (ver nota abajo)
            ]],
            ['slug' => 'wisc_v', 'label' => 'WISC-V (Psicología)', 'for' => 'asistente', 'cond' => ['require_cargo_in' => ['Psicólogo(a)']]],
            ['slug' => 'certificado_experiencia', 'label' => 'Certificado de experiencia', 'for' => 'conditional', 'cond' => ['optional_min_anios_experiencia' => 1]],
        ];

        $order = 1;
        foreach ($catalog as $row) {
            DocumentType::updateOrCreate(
                ['slug' => $row['slug']],
                [
                    'label'        => $row['label'],
                    'required_for' => $row['for'],
                    'conditions'   => $row['cond'] ?? null,
                    'template_path' => $row['template'] ?? null,
                    'sort_order'   => $order++,
                ]
            );
        }
    }
}
