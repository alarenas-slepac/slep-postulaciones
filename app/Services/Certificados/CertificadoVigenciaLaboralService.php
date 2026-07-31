<?php

namespace App\Services\Certificados;

use App\Models\CertificadoContratoHistorico;
use App\Models\CertificadoImportacion;
use App\Support\RutChile;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class CertificadoVigenciaLaboralService
{
    /**
     * @return array<string, mixed>
     */
    public function resolver(string $rut, ?CarbonInterface $fechaEmision = null): array
    {
        $rutNormalizado = $this->normalizarRut($rut);
        $importacion = CertificadoImportacion::query()
            ->where('es_vigente', true)
            ->whereIn('estado', ['procesado', 'procesado_con_observaciones'])
            ->latest('activado_at')
            ->latest('id')
            ->first();

        if (! $importacion) {
            throw new DomainException(
                'No existe una base histórica activa para emitir certificados.'
            );
        }

        $contratos = CertificadoContratoHistorico::query()
            ->where('importacion_id', $importacion->id)
            ->where('rut_normalizado', $rutNormalizado)
            ->orderBy('fecha_ingreso')
            ->orderBy('id')
            ->get();

        if ($contratos->isEmpty()) {
            throw new DomainException(
                'El RUT consultado no registra contratos en la base histórica activa.'
            );
        }

        $resultado = $this->resolverDesdeContratos(
            $contratos,
            $fechaEmision ?? CarbonImmutable::now()
        );
        $resultado['importacion'] = $importacion;

        return $resultado;
    }

    /**
     * Método público para probar la regla de continuidad sin depender de la base de datos.
     *
     * @param  iterable<int, CertificadoContratoHistorico|array<string, mixed>|object>  $contratos
     * @return array<string, mixed>
     */
    public function resolverDesdeContratos(
        iterable $contratos,
        CarbonInterface $fechaEmision
    ): array {
        $fechaCorte = CarbonImmutable::instance($fechaEmision)->startOfDay();
        $filas = collect($contratos)
            ->map(fn ($contrato, int $indice) => $this->normalizarContrato($contrato, $indice))
            ->values();

        $activos = $filas
            ->filter(fn (array $fila) => $this->estaVigente($fila, $fechaCorte))
            ->sort(fn (array $a, array $b) => $this->compararRecientes($a, $b))
            ->values();

        if ($activos->isEmpty()) {
            throw new DomainException(
                'No existe un contrato vigente para el RUT consultado a la fecha de emisión.'
            );
        }

        /** @var array<string, mixed> $ancla */
        $ancla = $activos->first();
        $regimenAncla = $ancla['regimen_normalizado'];

        $mismoRegimen = $filas
            ->filter(fn (array $fila) => $fila['regimen_normalizado'] === $regimenAncla)
            ->sortBy([
                ['fecha_ingreso', 'asc'],
                ['orden', 'asc'],
            ])
            ->values();

        $paraContinuidad = $mismoRegimen
            ->reject(fn (array $fila) => $this->esReemplazoExcluido($fila, $mismoRegimen))
            ->values();

        $cadena = $this->cadenaQueContiene($paraContinuidad, $ancla['clave']);
        if ($cadena === null) {
            throw new DomainException(
                'No fue posible determinar la continuidad del contrato vigente.'
            );
        }

        $activosMismoRegimen = $activos
            ->filter(fn (array $fila) => $fila['regimen_normalizado'] === $regimenAncla)
            ->values();

        $establecimientos = $activosMismoRegimen
            ->unique(fn (array $fila) => $fila['establecimiento_normalizado']
                .'|'.$fila['comuna_normalizada'])
            ->map(fn (array $fila) => [
                'establecimiento' => $fila['establecimiento'],
                'comuna' => $fila['comuna'],
                'fecha_ingreso' => $fila['fecha_ingreso']->format('Y-m-d'),
            ])
            ->values()
            ->all();

        $contratosSnapshot = collect($cadena['filas'])
            ->sort(fn (array $a, array $b) => $this->compararRecientes($a, $b))
            ->map(fn (array $fila) => [
                'fila_origen' => $fila['fila_origen'],
                'establecimiento' => $fila['establecimiento'],
                'comuna' => $fila['comuna'],
                'fecha_ingreso' => $fila['fecha_ingreso']->format('Y-m-d'),
                'fecha_finiquito' => $fila['fecha_finiquito']?->format('Y-m-d'),
                'termino_indefinido' => $fila['termino_indefinido'],
                'calidad_juridica' => $fila['calidad_juridica'],
                'regimen_juridico' => $fila['regimen_juridico'],
            ])
            ->values()
            ->all();

        return [
            'rut_normalizado' => $ancla['rut_normalizado'],
            'rut_formateado' => $this->formatearRut($ancla['rut_normalizado']),
            'nombre' => $ancla['nombre'],
            'fecha_emision' => $fechaCorte,
            'fecha_antiguedad' => $cadena['inicio'],
            'calidad_juridica' => $ancla['calidad_juridica'],
            'regimen_juridico' => $ancla['regimen_juridico'],
            'establecimientos' => $establecimientos,
            'contratos' => $contratosSnapshot,
        ];
    }

    /**
     * @param  CertificadoContratoHistorico|array<string, mixed>|object  $contrato
     * @return array<string, mixed>
     */
    private function normalizarContrato(mixed $contrato, int $indice): array
    {
        $valor = static function (mixed $fuente, string $campo): mixed {
            if (is_array($fuente)) {
                return $fuente[$campo] ?? null;
            }

            return $fuente->{$campo} ?? null;
        };

        $ingreso = $this->fecha($valor($contrato, 'fecha_ingreso'));
        if (! $ingreso) {
            throw new DomainException('Existe un contrato sin fecha de ingreso válida.');
        }

        $indefinido = (bool) $valor($contrato, 'termino_indefinido');
        $finiquito = $indefinido
            ? null
            : $this->fecha($valor($contrato, 'fecha_finiquito'));
        $rut = Str::upper((string) preg_replace(
            '/[^0-9Kk]/',
            '',
            (string) $valor($contrato, 'rut_normalizado')
        ));
        $establecimiento = trim((string) $valor($contrato, 'establecimiento'));
        $comuna = trim((string) $valor($contrato, 'comuna'));
        $calidad = trim((string) $valor($contrato, 'calidad_juridica'));
        $regimen = trim((string) $valor($contrato, 'regimen_juridico'));
        $id = $valor($contrato, 'id');
        $filaOrigen = $valor($contrato, 'fila_origen');

        return [
            'clave' => $id !== null ? 'id:'.$id : 'fila:'.($filaOrigen ?? $indice),
            'orden' => (int) ($id ?? $filaOrigen ?? $indice),
            'fila_origen' => $filaOrigen,
            'rut_normalizado' => $rut,
            'nombre' => trim((string) $valor($contrato, 'nombre')),
            'establecimiento' => $establecimiento,
            'establecimiento_normalizado' => $this->normalizarTexto($establecimiento),
            'comuna' => $comuna,
            'comuna_normalizada' => $this->normalizarTexto($comuna),
            'fecha_ingreso' => $ingreso,
            'fecha_finiquito' => $finiquito,
            'termino_indefinido' => $indefinido,
            'calidad_juridica' => $calidad,
            'calidad_normalizada' => $this->normalizarTexto($calidad),
            'regimen_juridico' => $regimen,
            'regimen_normalizado' => $this->normalizarTexto($regimen),
        ];
    }

    private function estaVigente(array $fila, CarbonImmutable $fecha): bool
    {
        return $fila['fecha_ingreso']->lte($fecha)
            && ($fila['termino_indefinido']
                || ($fila['fecha_finiquito'] && $fila['fecha_finiquito']->gte($fecha)));
    }

    private function compararRecientes(array $a, array $b): int
    {
        $porFecha = $b['fecha_ingreso']->getTimestamp()
            <=> $a['fecha_ingreso']->getTimestamp();

        return $porFecha !== 0 ? $porFecha : ($b['orden'] <=> $a['orden']);
    }

    private function esReemplazoExcluido(array $fila, Collection $mismoRegimen): bool
    {
        $destino = match ($fila['calidad_normalizada']) {
            'REEMPLAZO DOCENTE', 'REEMPLAZO CONTRATA' => 'CONTRATA',
            'REEMPLAZO' => 'PLAZO FIJO',
            default => null,
        };

        if ($destino === null) {
            return false;
        }

        return $mismoRegimen->contains(
            fn (array $posterior) => $posterior['fecha_ingreso']->gt($fila['fecha_ingreso'])
                && $posterior['calidad_normalizada'] === $destino
        );
    }

    /**
     * @return array{inicio: CarbonImmutable, fin: ?CarbonImmutable, filas: array<int, array<string, mixed>>}|null
     */
    private function cadenaQueContiene(Collection $filas, string $claveAncla): ?array
    {
        $cadenas = [];

        foreach ($filas as $fila) {
            $ultima = count($cadenas) - 1;
            if ($ultima < 0 || ! $this->esContiguo($cadenas[$ultima], $fila)) {
                $cadenas[] = [
                    'inicio' => $fila['fecha_ingreso'],
                    'fin' => $fila['termino_indefinido'] ? null : $fila['fecha_finiquito'],
                    'filas' => [$fila],
                ];

                continue;
            }

            $cadenas[$ultima]['filas'][] = $fila;
            if ($cadenas[$ultima]['fin'] !== null) {
                if ($fila['termino_indefinido']) {
                    $cadenas[$ultima]['fin'] = null;
                } elseif (
                    $fila['fecha_finiquito']
                    && $fila['fecha_finiquito']->gt($cadenas[$ultima]['fin'])
                ) {
                    $cadenas[$ultima]['fin'] = $fila['fecha_finiquito'];
                }
            }
        }

        foreach ($cadenas as $cadena) {
            if (collect($cadena['filas'])->contains(
                fn (array $fila) => $fila['clave'] === $claveAncla
            )) {
                return $cadena;
            }
        }

        return null;
    }

    private function esContiguo(array $cadena, array $fila): bool
    {
        if ($cadena['fin'] === null) {
            return true;
        }

        return $fila['fecha_ingreso']->lte($cadena['fin']->addDay());
    }

    private function normalizarRut(string $rut): string
    {
        $normalizado = RutChile::normalize($rut);
        if (! $normalizado || ($normalizado['status'] ?? null) !== 'ok') {
            throw new DomainException('El RUT ingresado no es válido.');
        }

        return Str::upper((string) preg_replace(
            '/[^0-9Kk]/',
            '',
            (string) $normalizado['rut']
        ));
    }

    private function formatearRut(string $rut): string
    {
        if (mb_strlen($rut) < 2) {
            return $rut;
        }

        $cuerpo = mb_substr($rut, 0, -1);
        $dv = mb_substr($rut, -1);

        return number_format((int) $cuerpo, 0, ',', '.').'-'.$dv;
    }

    private function normalizarTexto(string $valor): string
    {
        return Str::of($valor)
            ->ascii()
            ->upper()
            ->replaceMatches('/\s+/', ' ')
            ->trim()
            ->toString();
    }

    private function fecha(mixed $valor): ?CarbonImmutable
    {
        if ($valor instanceof CarbonInterface) {
            return CarbonImmutable::instance($valor)->startOfDay();
        }

        if ($valor instanceof \DateTimeInterface) {
            return CarbonImmutable::instance($valor)->startOfDay();
        }

        $texto = trim((string) ($valor ?? ''));
        if ($texto === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($texto)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }
}
