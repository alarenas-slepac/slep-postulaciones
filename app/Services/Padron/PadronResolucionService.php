<?php

namespace App\Services\Padron;

use App\Models\PadronRevision;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class PadronResolucionService
{
    public function __construct(private PadronRevisionService $revisiones) {}

    public function disponible(): bool
    {
        return Schema::hasTable('padron_revision_decisiones')
            && Schema::hasColumn('padron_revisiones', 'aplicada_at');
    }

    public function historial(PadronRevision $revision): Collection
    {
        return DB::table('padron_revision_decisiones')->where('padron_revision_id', $revision->id)->orderBy('id')->get();
    }

    public function decisiones(PadronRevision $revision): Collection
    {
        return $this->historial($revision)->keyBy('padron_revision_fila_id');
    }

    /** Selecciones efectivas; las propuestas originales no se sobrescriben. */
    public function selecciones(Collection $filas, Collection $decisiones): array
    {
        $out = [];
        foreach ($filas as $fila) {
            if ($fila->fila_excel === null) {
                if ($this->conservada($fila, $decisiones)) { $out[$fila->id] = (int) $fila->personal_id; }
                continue;
            }
            if (in_array($fila->accion, ['error', PadronReemplazosVigentes::OMITIDO], true)) {
                continue;
            }
            if ($fila->accion === 'revision_manual') {
                if ($decisiones->has($fila->id)) {
                    $out[$fila->id] = $decisiones[$fila->id]->personal_id;
                }
            } else {
                $out[$fila->id] = $fila->personal_id;
            }
        }

        return $out;
    }

    public function conservada(object $fila, Collection $decisiones): bool
    {
        return $fila->fila_excel === null && in_array($fila->accion, ['baja_propuesta', 'ausencia_por_revisar'], true)
            && $fila->personal_id && (int) ($decisiones->get($fila->id)?->personal_id ?? 0) === (int) $fila->personal_id;
    }

    /** Propuestas efectivas en memoria. No inventa números de fila Excel ni cambia el análisis original. */
    public function entrantes(PadronRevision $revision, Collection $filas, Collection $decisiones): Collection
    {
        $entrantes = $filas->whereNotNull('fila_excel')->values();
        $conservadas = $filas->filter(fn ($fila) => $this->conservada($fila, $decisiones));
        if ($conservadas->isEmpty()) { return $entrantes; }
        // Leer únicamente las imágenes de las ausencias seleccionadas, no duplicar todo el padrón en memoria.
        $originales = $revision->filas()->whereIn('id', $conservadas->pluck('id'))->get(['id', 'anterior'])->keyBy('id');
        foreach ($conservadas as $fila) {
            $propuesta = clone $fila;
            $propuesta->datos = $this->datosConservados($revision, $originales[$fila->id]->anterior ?? []);
            $propuesta->accion = 'conservacion_propuesta';
            $entrantes->push($propuesta);
        }
        return $entrantes;
    }

    public function datosConservados(PadronRevision $revision, array $anterior): array
    {
        $id = 'ID '.($anterior['id'] ?? 'sin identificar').': ';
        if (! $revision->anio || $revision->mes < 1 || $revision->mes > 12) {
            $this->fail($id.'la revisión no tiene un período de carga válido.');
        }
        if ((int) ($anterior['anio'] ?? 0) !== (int) $revision->anio) {
            $this->fail($id.'el año contractual '.($anterior['anio'] ?? 'sin informar').' no coincide con el año de carga '.$revision->anio.'. No se modifica historia de otro año con esta opción.');
        }
        if ((int) ($anterior['mes'] ?? 0) < 1 || (int) ($anterior['mes'] ?? 0) > 12) {
            $this->fail($id.'el mes contractual no es válido; revise el registro de origen.');
        }
        if ((int) $anterior['mes'] > (int) $revision->mes) {
            $this->fail($id.'el mes contractual '.$anterior['mes'].' es posterior al mes de carga '.$revision->mes.'. No se retrocede el período con esta opción.');
        }
        if (! ($anterior['vigente'] ?? true)) {
            $this->fail($id.'el contrato está inactivo. Esta opción conserva contratos vigentes, no los reactiva.');
        }
        if (app(PadronConciliador::class)->contratoRegularHistorico($anterior) === null) {
            $this->fail($id.'el tipo de contrato «'.($anterior['tipocontrato'] ?? 'sin informar').'» con financiamiento «'.($anterior['financiamiento'] ?? 'sin informar').'» no es regular reconocido. Los sufijos históricos SEP/PIE deben coincidir con el financiamiento; no se admiten reemplazos ni suplencias. Revise el Excel.');
        }
        if (! is_numeric($anterior['jornada'] ?? null) || (float) $anterior['jornada'] < 0) {
            $this->fail($id.'la jornada contractual debe ser numérica y no negativa.');
        }
        $inicio = sprintf('%04d-%02d-01', $revision->anio, $revision->mes);
        if (! empty($anterior['fecha_termino']) && $anterior['fecha_termino'] < $inicio) {
            $this->fail($id.'el contrato terminó el '.$anterior['fecha_termino'].', antes del mes de carga. No se puede prorrogar su fecha de término con esta opción; revise el Excel.');
        }
        $data = array_intersect_key($anterior, array_flip([...PadronExcelReader::REQUIRED, 'tramo', 'fecha_antiguedad']));
        $data['rut'] = PadronConciliador::rut($anterior['rut'] ?? '');
        $data['anio'] = (int) $revision->anio;
        $data['mes'] = (int) $revision->mes;
        return $data;
    }

    /** Reconoce etiquetas históricas solo en las conservaciones validadas, sin alterar sus datos. */
    public function tipoPropuesto(object $fila): string
    {
        if ($fila->fila_excel === null && $fila->accion === 'conservacion_propuesta'
            && app(PadronConciliador::class)->contratoRegularHistorico($fila->datos) !== null) {
            return 'regular';
        }
        return PadronConciliador::tipo($fila->datos);
    }

    public function resolver(PadronRevision $revision, int $filaId, ?int $personalId, string $motivo, int $usuario, int $decisionAnterior = 0): void
    {
        $this->registrarDecisiones($revision, [[
            'fila' => $filaId, 'personal_id' => $personalId,
            'justificacion' => $motivo, 'decision_anterior' => $decisionAnterior,
        ]], $usuario);
    }

    public function resolverVarias(PadronRevision $revision, string $rut, array $decisiones, int $usuario): int
    {
        $rut = PadronConciliador::rut($rut);
        if ($rut === '') {
            $this->fail('Indique el RUT de las correspondencias que desea resolver juntas.');
        }

        return $this->registrarDecisiones($revision, $decisiones, $usuario, $rut);
    }

    private function registrarDecisiones(PadronRevision $revision, array $entradas, int $usuario, ?string $rut = null): int
    {
        Validator::make(['decisiones' => $entradas], [
            'decisiones' => ['required', 'array', 'min:1', 'max:50'],
            'decisiones.*' => ['required', 'array'],
            'decisiones.*.fila' => ['required', 'integer', 'min:1', 'distinct'],
            // null solo representa una elección explícita de nueva línea/baja.
            'decisiones.*.personal_id' => ['present', 'nullable', 'integer', 'min:1'],
            'decisiones.*.justificacion' => ['required', 'string', 'min:10', 'max:2000'],
            'decisiones.*.decision_anterior' => ['required', 'integer', 'min:0'],
        ])->validate();
        if (! $this->disponible()) {
            $this->fail('Ejecute las migraciones de revisión del padrón con PHP 8.3 antes de resolver coincidencias.');
        }

        return DB::transaction(function () use ($revision, $entradas, $usuario, $rut): int {
            $revision = PadronRevision::whereKey($revision->id)->lockForUpdate()->firstOrFail();
            if ($revision->aplicada_at || $revision->errores || $this->revisiones->stale($revision)) {
                $this->fail('La revisión está cerrada, contiene errores o cambió la base. Genere un nuevo análisis.');
            }
            $filas = $revision->filas()->orderBy('id')->get(['id', 'fila_excel', 'accion', 'personal_id', 'rut']);
            $objetivos = $revision->filas()->whereIn('id', array_column($entradas, 'fila'))->get()->keyBy('id');
            $decisiones = $this->decisiones($revision);
            $selecciones = $this->selecciones($filas, $decisiones);
            // Validar la selección final completa, independiente del orden del lote.
            foreach ($entradas as $entrada) {
                $fila = $objetivos->get((int) $entrada['fila']);
                if ($fila) {
                    $selecciones[$fila->id] = $entrada['personal_id'] === null ? null : (int) $entrada['personal_id'];
                }
            }
            $nuevas = [];
            foreach ($entradas as $entrada) {
                $filaId = (int) $entrada['fila'];
                $personalId = $entrada['personal_id'] === null ? null : (int) $entrada['personal_id'];
                $motivo = trim($entrada['justificacion']);
                $decisionAnterior = (int) $entrada['decision_anterior'];
                $fila = $objetivos->get($filaId);
                if (! $fila || ! in_array($fila->accion, ['revision_manual', 'ausencia_por_revisar', 'baja_propuesta'], true)) {
                    $this->fail('Solo puede resolver filas ambiguas o ausencias pertenecientes a esta revisión.');
                }
                if ($rut !== null && PadronConciliador::rut($fila->rut) !== $rut) {
                    $this->fail('Todas las correspondencias del envío deben pertenecer al mismo RUT.');
                }
                if (mb_strlen($motivo) < 10 || mb_strlen($motivo) > 2000) {
                    $this->fail('Ingrese una justificación de entre 10 y 2.000 caracteres.');
                }
                if ($fila->fila_excel && PadronConciliador::tipo($fila->datos) === 'por_clasificar') {
                    $this->fail('Corrija el tipo de contrato desconocido en el Excel y vuelva a analizar.');
                }
                $candidatos = collect($fila->candidatos)->pluck('id')->map(fn ($id) => (int) $id)->all();
                if ($personalId !== null && ($fila->fila_excel ? ! in_array($personalId, $candidatos, true) : $personalId !== (int) $fila->personal_id)) {
                    $this->fail('El ID seleccionado no es un candidato válido para esta fila.');
                }
                if (! $fila->fila_excel && $personalId !== null) {
                    $datos = $this->datosConservados($revision, $fila->anterior ?? []);
                    $actualPersonal = DB::table('reemplazos_personal')->find($personalId);
                    if (! $actualPersonal || PadronConciliador::rut($actualPersonal->rut) !== PadronConciliador::rut($fila->rut)
                        || $datos['rut'] !== PadronConciliador::rut($fila->rut) || (int) ($fila->anterior['id'] ?? 0) !== $personalId) {
                        $this->fail('El contrato no corresponde a la identidad de esta ausencia. Genere un nuevo análisis.');
                    }
                    if (app(PadronBajaAsignacionesService::class)->ultimas($revision)->get(PadronConciliador::rut($fila->rut))?->confirmada) {
                        $this->fail('Retire primero la confirmación de baja con liberación del RUT antes de conservar sus contratos.');
                    }
                }
                $actual = $decisiones->get($filaId);
                // Reenvío del mismo formulario: no duplica decisiones ni auditoría.
                $identica = $actual && ($actual->personal_id === null ? null : (int) $actual->personal_id) === $personalId
                    && $actual->justificacion === $motivo && (int) $actual->resuelta_por === $usuario;
                if (! $identica && (int) ($actual->id ?? 0) !== $decisionAnterior) {
                    $this->fail('Otra decisión fue registrada después de abrir la pantalla. Recargue antes de corregirla.');
                }
                $otras = $selecciones;
                unset($otras[$filaId]);
                if ($personalId !== null && in_array($personalId, array_map('intval', array_filter($otras, fn ($id) => $id !== null)), true)) {
                    $this->fail('El ID ya está seleccionado en otra fila. No puede actualizar dos contratos sobre el mismo registro.');
                }
                if (! $fila->fila_excel && in_array((int) $fila->personal_id, array_map('intval', array_filter($otras, fn ($id) => $id !== null)), true)) {
                    $this->fail('Esta ausencia ya está cubierta por una fila del archivo. No corresponde confirmar su baja.');
                }
                if ($identica) {
                    continue;
                }
                $nuevas[] = [
                    'padron_revision_id' => $revision->id, 'padron_revision_fila_id' => $filaId,
                    'personal_id' => $personalId, 'justificacion' => $motivo, 'resuelta_por' => $usuario,
                    'created_at' => now(), 'updated_at' => now(),
                ];
            }
            if ($nuevas) {
                DB::table('padron_revision_decisiones')->insert($nuevas);
                $ausencias = $objetivos->whereNull('fila_excel');
                if ($ausencias->isNotEmpty()) { $this->actualizarExcesosConservados($revision, $filas, $ausencias->pluck('rut')->all()); }
            }

            return count($nuevas);
        });
    }

    private function actualizarExcesosConservados(PadronRevision $revision, Collection $filas, array $ruts): void
    {
        $ruts = array_unique(array_map([PadronConciliador::class, 'rut'], $ruts));
        $ids = $filas->filter(fn ($fila) => in_array(PadronConciliador::rut($fila->rut), $ruts, true))->pluck('id');
        $filasRut = $revision->filas()->whereIn('id', $ids)->get(['id', 'fila_excel', 'accion', 'personal_id', 'rut', 'datos']);
        $entrantes = $this->entrantes($revision, $filasRut, $this->decisiones($revision))
            ->reject(fn ($fila) => $fila->accion === PadronReemplazosVigentes::OMITIDO)
            ->groupBy(fn ($fila) => PadronConciliador::rut($fila->rut));
        $excesos = $revision->excesos ?? [];
        foreach ($ruts as $rut) {
            unset($excesos[$rut]);
            $grupo = $entrantes->get($rut, collect());
            $total = (float) $grupo->sum(fn ($fila) => $fila->datos['jornada'] ?? 0);
            if ($total > 44 && $grupo->contains(fn ($fila) => PadronConciliador::docente($fila->datos))) {
                $autorizacion = DB::table('padron_revision_autorizaciones')->where('padron_revision_id', $revision->id)->where('rut', $rut)->first();
                if ($autorizacion && (float) $autorizacion->jornada_total !== $total) {
                    $this->fail('La conservación cambiaría un total ya autorizado para este RUT. Genere una nueva revisión para autorizar el nuevo total; no se modificó la decisión anterior.');
                }
                $excesos[$rut] = ['total' => $total, 'exceso' => $total - 44,
                    'filas' => $grupo->whereNotNull('fila_excel')->pluck('fila_excel')->all(),
                    'ids_conservados' => $grupo->where('accion', 'conservacion_propuesta')->pluck('personal_id')->all()];
            }
        }
        $revision->forceFill(['excesos' => $excesos])->save();
    }

    public function resumen(Collection $filas, Collection $decisiones): array
    {
        $selecciones = $this->selecciones($filas, $decisiones);
        $usados = array_fill_keys(array_filter($selecciones, fn ($id) => $id !== null), true);
        $rutsConservados = [];
        foreach ($filas as $fila) {
            if ($this->conservada($fila, $decisiones)) {
                $rutsConservados[PadronConciliador::rut($fila->rut)] = true;
            }
        }
        $estados = [];
        foreach ($filas as $fila) {
            $estados[$fila->id] = match (true) {
                $fila->accion === PadronReemplazosVigentes::OMITIDO => 'omitida_por_vigencia',
                $fila->accion === 'error' => 'error',
                $this->conservada($fila, $decisiones) => 'conservada',
                ! $fila->fila_excel && isset($usados[$fila->personal_id]) => 'ausencia_vinculada',
                $fila->accion === 'baja_propuesta' && ! $decisiones->has($fila->id)
                    && isset($rutsConservados[PadronConciliador::rut($fila->rut)]) => 'pendiente',
                $fila->accion === 'revision_manual' && ! $decisiones->has($fila->id) => 'pendiente',
                $fila->accion === 'ausencia_por_revisar' && ! $decisiones->has($fila->id) => 'pendiente',
                $decisiones->has($fila->id) => 'resuelta',
                default => 'propuesta_automatica',
            };
        }

        return ['selecciones' => $selecciones, 'estados' => $estados, 'totales' => array_count_values($estados)];
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['revision' => $message]);
    }
}
