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
     * de título o ausencia de declaración, la asignación se convierte por 65/35.
     */
    public static function conversionNt(
        EstablecimientoCurso $curso,
        float $horasAula,
        array $persona,
        ?string $proporcionConfigurada = null
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

        $conJec = self::reglaConfiguradaConJec($proporcionConfigurada)
            ?? self::cursoTieneJec($curso);
        $especial = self::conversionEspecial($horasAula, $conJec);

        return array_merge($especial, [
            'titulo_declarado' => $perfil['titulo_declarado'],
            'fuente_titulo' => $perfil['fuente_titulo'],
            'motivo' => ($conJec ? 'NT1/NT2 con JEC' : 'NT1/NT2 sin JEC')
                .': se aplica la regla especial exclusivamente por registrar el título "Pedagogía en Educación de Párvulos" en Declaración de Sostenedores.'
                .($especial['horas_libre_disposicion_parvularia'] > 0
                    ? ' Las horas por sobre 32 se convierten mediante 65/35.'
                    : ''),
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

    private static function conversionEspecial(float $horasAula, bool $conJec): array
    {
        $horasBase = min($horasAula, 32.0);
        $horasLibreDisposicion = max(0.0, $horasAula - 32.0);
        $contratoBaseReferencia = $conJec ? 50.0 : 47.0;
        $cronologicasBaseReferencia = $conJec ? 32.25 : 30.0;
        $contratoBase = round(($horasBase / 32.0) * $contratoBaseReferencia, 4);
        $cronologicasBase = round(($horasBase / 32.0) * $cronologicasBaseReferencia, 4);
        $cronologicasLibreDisposicion = round($horasLibreDisposicion * 45 / 60, 4);
        $contratoLibreDisposicion = $horasLibreDisposicion > 0
            ? round($cronologicasLibreDisposicion / 0.65, 4)
            : 0.0;
        $contrato = round($contratoBase + $contratoLibreDisposicion, 4);
        $regimen = $conJec ? 'con_jec' : 'sin_jec';
        $regimenLabel = $conJec ? 'Con JEC' : 'Sin JEC';

        return [
            'proporcion' => $conJec
                ? 'parvularia_jec_especial_65_35_ld'
                : 'parvularia_sin_jec_especial_65_35_ld',
            'proporcion_label' => 'NT '.$regimenLabel.' · regla especial por profesión',
            'origen_proporcion' => 'regla_especial_parvularia_por_profesion',
            'origen_proporcion_label' => 'Pedagogía en Educación de Párvulos',
            'horas_aula_cronologicas' => round($cronologicasBase + $cronologicasLibreDisposicion, 4),
            'horas_contrato_equivalente' => $contrato,
            'horas_contrato_equivalente_redondeado' => $contrato > 0 ? (float) ceil($contrato) : 0.0,
            'horas_base_parvularia' => $horasBase,
            'horas_libre_disposicion_parvularia' => $horasLibreDisposicion,
            'cronologicas_base_parvularia' => $cronologicasBase,
            'cronologicas_libre_disposicion_parvularia' => $cronologicasLibreDisposicion,
            'contrato_base_parvularia' => $contratoBase,
            'contrato_libre_disposicion_parvularia' => $contratoLibreDisposicion,
            'regimen_parvularia' => $regimen,
        ];
    }

    private static function reglaConfiguradaConJec(?string $proporcion): ?bool
    {
        return match (trim((string) $proporcion)) {
            'nt_jec', 'parvularia_jec_especial_65_35_ld' => true,
            'nt_sin_jec', 'parvularia_sin_jec_especial_65_35_ld' => false,
            default => null,
        };
    }

    private static function cursoTieneJec(EstablecimientoCurso $curso): bool
    {
        $plan = $curso->planEstudio;
        $texto = Str::of(collect([
            $curso->regimen_jec ?? null,
            $curso->jornada ?? null,
            $curso->tipo_jornada ?? null,
            $plan?->nombre ?? null,
            $plan?->regimen_jec ?? null,
            $plan?->jornada ?? null,
            $plan?->tipo_jornada ?? null,
        ])->filter()->implode(' '))->ascii()->upper()->squish()->toString();

        if (str_contains($texto, 'SIN JEC')) {
            return false;
        }

        return str_contains($texto, 'CON JEC')
            || str_contains($texto, 'JECD')
            || str_contains($texto, 'JEC');
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
