<?php

namespace App\Support;

use App\Models\EstablecimientoCurso;
use Illuminate\Support\Str;

class DotacionProfesionDocenteResolver
{
    public const TITULO_EDUCACION_PARVULOS = 'PEDAGOGIA EN EDUCACION DE PARVULOS';

    /**
     * Determina la regla contractual aplicable a una asignación de NT1/NT2.
     *
     * La regla especial de Educación Parvularia se aplica exclusivamente cuando
     * el título registrado en Declaración de Sostenedores corresponde a
     * "Pedagogía en Educación de Párvulos". Para cualquier otro título, ausencia
     * de título o ausencia de declaración, la equivalencia se obtiene por 65/35.
     * El controlador restringe nuevas coberturas sin JEC a Educadoras de Párvulos.
     */
    public static function conversionNt(
        EstablecimientoCurso $curso,
        float $horasAula,
        array $persona,
        ?string $proporcionConfigurada = null,
        ?array $contextoGrupo = null
    ): ?array {
        if (! self::esCursoNt($curso)) {
            return null;
        }

        $horasAula = round(max(0.0, $horasAula), 2);
        $perfil = self::perfilTitulo($persona);

        if (! $perfil['es_educacion_parvulos']) {
            $conversion = DocenteHorasNoLectivasCalculator::contratoRequeridoDesdeHorasAula(
                DocenteHorasNoLectivasCalculator::PROPORCION_GENERAL,
                $horasAula
            );

            return [
                'proporcion' => DocenteHorasNoLectivasCalculator::PROPORCION_GENERAL,
                'proporcion_label' => '65/35',
                'origen_proporcion' => $perfil['titulo_declarado'] !== ''
                    ? 'profesion_distinta_educacion_parvulos'
                    : 'profesion_no_declarada',
                'origen_proporcion_label' => $perfil['titulo_declarado'] !== ''
                    ? 'Título distinto de Pedagogía en Educación de Párvulos'
                    : 'Sin profesión declarada',
                'horas_aula_cronologicas' => round($horasAula * 45 / 60, 4),
                'horas_contrato_equivalente' => (float) ($conversion['horas_contrato'] ?? 0),
                'horas_contrato_equivalente_redondeado' => (float) ($conversion['horas_contrato'] ?? 0),
                'titulo_declarado' => $perfil['titulo_declarado'],
                'fuente_titulo' => $perfil['fuente_titulo'],
                'motivo' => $perfil['titulo_declarado'] !== ''
                    ? 'Asignación en NT1/NT2 convertida por 65/35 porque el título declarado es "'.$perfil['titulo_declarado'].'".'
                    : 'Asignación en NT1/NT2 convertida por 65/35 porque no existe profesión declarada en Declaración de Sostenedores.',
                'conversion_tabla' => $conversion,
            ];
        }

        $conJec = DotacionParvulariaCalculator::conJec($curso, $proporcionConfigurada);
        $totalPlan = (float) ($contextoGrupo['parvularia_horas_plan_total']
            ?? DotacionEstablecimientoCalculator::horasCurso($curso)['horas'] ?? 0);
        $base = (float) ($contextoGrupo['parvularia_base_contrato']
            ?? DotacionParvulariaCalculator::base($curso, $conJec));
        $especial = DotacionParvulariaCalculator::convertir($horasAula, $totalPlan, $base, $conJec);

        return array_merge($especial, [
            'titulo_declarado' => $perfil['titulo_declarado'],
            'fuente_titulo' => $perfil['fuente_titulo'],
            'motivo' => $especial['motivo'].' Regla exclusiva para Pedagogía en Educación de Párvulos.',
        ]);
    }

    public static function perfilTitulo(array $persona): array
    {
        $declaracion = $persona['declaracion'] ?? null;
        $titulo = trim((string) ($declaracion?->nombre_titulo ?? $persona['titulo'] ?? ''));
        $normalizado = self::normalizarTitulo($titulo);

        return [
            'titulo_declarado' => $titulo,
            'titulo_normalizado' => $normalizado,
            'es_educacion_parvulos' => $normalizado === self::TITULO_EDUCACION_PARVULOS,
            'es_educacion_diferencial' => (bool) preg_match(
                '/^(?:(?:PEDAGOGIA EN |PROFESOR(?:A)? (?:DE |EN )?|LICENCIATURA EN )?EDUCACION DIFERENCIAL|EDUCADOR(?:A| A)? DIFERENCIAL)(?: |$)/',
                $normalizado
            ),
            'fuente_titulo' => $declaracion ? 'Declaración de Sostenedores' : 'Sin declaración',
        ];
    }

    public static function esCursoNt(EstablecimientoCurso $curso): bool
    {
        $codigo = Str::of((string) ($curso->curso?->codigo ?? ''))->ascii()->upper()->trim()->toString();
        if (in_array($codigo, ['NT1', 'NT2'], true)) {
            return true;
        }

        $texto = Str::of(collect([
            $curso->curso?->nombre,
            $curso->curso?->nivel_educativo,
            $curso->nombre_seccion,
        ])->filter()->implode(' '))->ascii()->upper()->squish()->toString();

        return str_contains($texto, 'NT1') || str_contains($texto, 'NT2');
    }
    private static function normalizarTitulo(?string $titulo): string
    {
        return Str::of((string) $titulo)
            ->ascii()
            ->upper()
            ->replaceMatches('/[^A-Z0-9]+/', ' ')
            ->squish()
            ->toString();
    }
}
