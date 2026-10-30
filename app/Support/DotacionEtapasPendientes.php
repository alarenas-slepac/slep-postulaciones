<?php

namespace App\Support;

/** Explica el avance guardado sin consultar ni modificar datos o reglas de cálculo. */
class DotacionEtapasPendientes
{
    /** @return array<string, array<int, array{texto: string, filtros?: array}>> */
    public static function porEtapa(array $proceso, array $asignacion = []): array
    {
        $pendientes = [];
        foreach ($proceso['pasos'] ?? [] as $key => $paso) {
            if ($paso['completo'] ?? false) {
                continue;
            }

            $items = match ($key) {
                'planes' => self::planes($proceso),
                'subsectores' => self::subsectores($proceso),
                'combinaciones' => [[
                    'texto' => data_get($proceso, 'configuracion.decision_combinacion') === 'combinaciones_configuradas'
                        ? 'La decisión indica cursos combinados, pero falta registrar al menos un grupo activo.'
                        : 'Falta guardar la decisión sobre si se combinarán cursos en este período.',
                ]],
                'normativas' => collect($proceso['funciones_normativas'] ?? [])
                    ->reject(fn ($funcion) => $funcion['definida'] ?? false)
                    ->map(fn ($funcion) => ['texto' => 'Definir si se utilizará: '.($funcion['titulo'] ?? 'Función normativa').'.'])
                    ->values()->all(),
                'maximos' => self::maximos($proceso),
                'asignacion' => self::asignacion($proceso, $asignacion),
                'justificaciones' => [[
                    'texto' => (data_get($proceso, 'justificaciones.disponible', false))
                        ? 'Registre o actualice los fundamentos de supresión de horas titulares y no renovación de contrata para los saldos actuales de Plan de estudio, Parvularia y PIE.'
                        : 'El registro de justificaciones no está disponible. Solicite su habilitación a Administración.',
                ]],
                default => [],
            };

            $pendientes[$key] = $items ?: [['texto' => 'Revise y guarde la configuración pendiente de esta etapa.']];
        }

        return $pendientes;
    }

    private static function planes(array $proceso): array
    {
        $estado = $proceso['estado_planes'] ?? [];
        if (array_key_exists('total', $estado) && (int) $estado['total'] === 0) {
            return [['texto' => 'Falta registrar cursos activos con matrícula para configurar sus planes.']];
        }

        $items = collect($estado['pendientes'] ?? [])
            ->map(fn ($curso) => ['texto' => $curso.': revisar y completar el plan enviado y sus bloques flexibles.'])
            ->values()->all();
        $libre = (int) ($estado['libre_disposicion_pendiente'] ?? 0);
        if ($libre > 0) {
            $items[] = ['texto' => 'Falta completar las horas de libre disposición en '.$libre.' curso(s).'];
        }

        return $items;
    }

    private static function subsectores(array $proceso): array
    {
        $resumen = $proceso['docentes_subsector'] ?? [];
        if (array_key_exists('disponible', $resumen) && ! $resumen['disponible']) {
            return [['texto' => 'El registro de docentes por asignatura no está disponible. Solicite su habilitación a Administración.']];
        }

        $asignaturas = $resumen['asignaturas'] ?? collect($resumen['grupos'] ?? [])
            ->flatMap(fn ($grupo) => $grupo['asignaturas'] ?? []);

        return collect($asignaturas)->reject(fn ($item) => $item['completo'] ?? false)
            ->map(function ($item): array {
                $nivel = DotacionDocentesSubsector::NIVELES[$item['nivel'] ?? ''] ?? 'Nivel sin identificar';
                $cursos = collect($item['cursos'] ?? [])->filter()->implode(' · ');

                return ['texto' => ($item['nombre'] ?? 'Asignatura').' · '.$nivel
                    .($cursos !== '' ? ' · '.$cursos : '').': falta asociar al menos un '
                    .(($item['nivel'] ?? '') === 'epja' ? 'docente o asistente habilitado.' : 'docente habilitado.')];
            })->values()->all();
    }

    private static function maximos(array $proceso): array
    {
        $items = [];
        foreach ($proceso['bloques'] ?? [] as $bloque) {
            $label = $bloque['label'] ?? 'Bloque';
            $maximo = $bloque['maximo'] ?? null;
            if ($maximo === null) {
                $items[] = ['texto' => $label.': falta definir el máximo autorizado.'];
            } elseif ($bloque['maximo_insuficiente'] ?? false) {
                $items[] = ['texto' => $label.': máximo autorizado '.self::horas($maximo)
                    .' h; se requieren '.self::horas($bloque['requeridas'] ?? 0).' h. Solicite revisión a un rol autorizado.'];
            }
        }

        return $items;
    }

    private static function asignacion(array $proceso, array $asignacion): array
    {
        $items = [];
        $bloquesPendientes = [];
        foreach ($proceso['bloques'] ?? [] as $key => $bloque) {
            if ((float) ($bloque['pendientes'] ?? 0) > 0.01) {
                $bloquesPendientes[$key] = true;
                $items[] = ['texto' => ($bloque['label'] ?? 'Bloque').': faltan '
                    .self::horas($bloque['pendientes']).' h de contrato de cobertura obligatoria.'];
            }
        }

        $normativas = collect($proceso['funciones_normativas'] ?? [])->keyBy('key');
        foreach ($asignacion['necesidades'] ?? [] as $grupo => $necesidades) {
            foreach ($necesidades as $item) {
                $key = (string) ($item['key'] ?? '');
                $bloque = $proceso['need_blocks'][$key] ?? null;
                // Sólo detallar necesidades obligatorias del bloque pendiente.
                if (! isset($bloquesPendientes[$bloque])
                    || ($grupo === 'funciones' && ((int) ($item['dotacion_funcion_id'] ?? 0) > 0
                        || ($item['tipo_asignacion'] ?? '') === 'otra_funcion'))
                    || ($normativas->has($key) && ! ($normativas->get($key)['se_utilizara'] ?? false))) {
                    continue;
                }

                $esPlan = $grupo === 'plan_estudio';
                $campo = $esPlan ? 'horas_plan_pendientes' : 'horas_contrato_pendientes';
                $saldo = (float) ($item[$campo] ?? 0);
                if ($saldo <= 0.01) {
                    continue;
                }

                $curso = (string) ($item['curso_label'] ?? '');
                $titulo = (string) ($item['titulo'] ?? 'Necesidad');
                $items[] = [
                    'texto' => ($curso !== '' ? $curso.' · ' : '').$titulo.': faltan '
                        .self::horas($saldo).($esPlan ? ' h aula.' : ' h de contrato.'),
                    'filtros' => ['asig_seccion' => $grupo, 'asig_curso' => $curso, 'asig_buscar' => $titulo],
                ];
            }
        }

        return $items;
    }

    private static function horas(mixed $value): string
    {
        return rtrim(rtrim(number_format((float) $value, 2, ',', '.'), '0'), ',');
    }
}
