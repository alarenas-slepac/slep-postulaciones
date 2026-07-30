<?php

namespace App\Support;

use App\Models\User;

class ProfileChecklist
{
    /**
     * Calcula % de avance del perfil y lista de campos faltantes
     * alineado con las reglas actuales del Front y del FormRequest.
     *
     * @return array{
     *   percent:int,
     *   total:int,
     *   complete:int,
     *   missing: array<int, array{anchor:string,label:string}>,
     *   ok: bool
     * }
     */
    public static function compute(User $user): array
    {
        $profile = $user->postulantProfile;

        $get = function (string $field) use ($profile) {
            return $profile?->{$field};
        };

        $missing  = [];
        $total    = 0;
        $complete = 0;

        $add = function (bool $isRequired, bool $ok, string $anchor, string $label) use (&$total, &$complete, &$missing) {
            if (! $isRequired) {
                return;
            }
            $total++;
            if ($ok) {
                $complete++;
            } else {
                $missing[] = ['anchor' => $anchor, 'label' => $label];
            }
        };

        // --- SIEMPRE REQUERIDOS (datos personales) ---
        $add(true, filled($get('email_contacto')),   '#anchor-email_contacto',   'Email de contacto');
        $add(true, filled($get('fecha_nacimiento')), '#anchor-fecha_nacimiento', 'Fecha de nacimiento');
        $add(true, filled($get('direccion')),        '#anchor-direccion',        'Dirección');
        $add(true, filled($get('region_code')),      '#anchor-region_code',      'Región');
        $add(true, filled($get('comuna_id')),        '#anchor-comuna_id',        'Comuna');
        $add(true, filled($get('nacionalidad')),     '#anchor-nacionalidad',     'Nacionalidad');
        $add(true, filled($get('telefono1')),        '#anchor-telefono1',        'Teléfono 1');
        $add(true, filled($get('genero')),           '#anchor-genero',           'Género');

        // --- Académicos base: SIEMPRE requeridos ---
        $nivel = (string) $get('nivel_estudios');
        $anios = $get('anios_experiencia');

        $add(true, filled($nivel),                       '#wrap-nivel_estudios',    'Nivel de estudios');
        $add(true, is_numeric($anios) || filled($anios), '#wrap-anios_experiencia', 'Años de experiencia');

        // --- Estamento ---
        $est = (string) $get('estamento');
        $add(true, filled($est), '#anchor-estamento', 'Estamento');

        if ($est === 'docente') {
            // ✅ Área: ahora se guarda como FK
            $areaId = $profile?->area_desempeno_id;
            $areaNombre = (string) ($profile?->area_desempeno_nombre ?? ''); // accessor que ya tienes

            $add(true, filled($areaId) || filled($profile?->area_desempeno), '#wrap-area_desempeno', 'Área de desempeño');

            // Normalizaciones usando el NOMBRE (no el campo antiguo)
            $esTP       = ($areaNombre === 'Docente Técnico Profesional');
            $esEducDiff = in_array($areaNombre, ['Educadora Diferencial', 'Educador(a) Diferencial'], true);

            $add($esEducDiff, filled($get('mencion')), '#wrap-mencion', 'Mención');
            $add($esTP, filled($get('especialidad_tp')), '#wrap-especialidad_tp', 'Especialidad TP');
        }

        if ($est === 'asistente') {
            $areaId = $profile?->area_desempeno_id;
            $areaNombre = (string) ($profile?->area_desempeno_nombre ?? ''); // accessor que ya tienes

            $add(true, filled($areaId) || filled($profile?->area_desempeno), '#wrap-area_desempeno', 'Área de desempeño');
        }

        // --- Reglas por NIVEL ---
        // Institución requerida si nivel ∈ {Técnico Nivel Superior, Universitaria}
        $instRequerida = in_array($nivel, ['Técnico Nivel Superior', 'Universitaria'], true);
        $add($instRequerida, filled($get('institucion_titulo')), '#wrap-institucion_titulo', 'Institución');

        // Fecha de titulación requerida si nivel ∈ {Técnico Nivel Superior, Universitaria}
        $fechaTitRequerida = in_array($nivel, ['Técnico Nivel Superior', 'Universitaria'], true);
        $add($fechaTitRequerida, filled($get('fecha_titulacion')), '#wrap-fecha_titulacion', 'Fecha de titulación');

        // Semestres y horas: SOLO si nivel = Universitaria
        $add($nivel === 'Universitaria', filled($get('semestres')),     '#wrap-semestres',     'Semestres cursados');
        $add($nivel === 'Universitaria', filled($get('horas_totales')), '#wrap-horas_totales', 'Horas totales');

        // --- Lugares de desempeño: al menos uno ---
        $hasLugares = $user->communes()->exists();
        $add(true, $hasLugares, '#anchor-lugares', 'Lugares de desempeño');

        // --- Datos previsionales y bancarios: SIEMPRE obligatorios ---
        $add(true, filled($get('prevision_afp')),     '#anchor-prevision_afp',     'Institución de previsión (AFP)');
        $add(true, filled($get('salud_institucion')), '#anchor-salud_institucion', 'Institución de salud');
        $add(true, filled($get('banco')),             '#anchor-banco',             'Banco');
        $add(true, filled($get('tipo_cuenta')),       '#anchor-tipo_cuenta',       'Tipo de cuenta');
        $add(true, filled($get('numero_cuenta')),     '#anchor-numero_cuenta',     'Nº de cuenta');

        $percent = $total > 0 ? (int) round(($complete / $total) * 100) : 100;

        return [
            'percent'  => $percent,
            'total'    => $total,
            'complete' => $complete,
            'missing'  => $missing,
            'ok'       => ($percent === 100),
        ];
    }
}
