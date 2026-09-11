<?php

namespace App\Services\Padron;

use App\Models\PadronRevision;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/** Decisiones de revisión. Solo el escritor transaccional libera asignaciones. */
class PadronBajaAsignacionesService
{
    public function instalado(): bool
    {
        return Schema::hasTable('padron_bajas_asignaciones') && Schema::hasTable('padron_asignacion_cambios');
    }

    public function trasladoInstalado(): bool
    {
        return $this->instalado()
            && Schema::hasColumn('padron_bajas_asignaciones', 'tipo')
            && Schema::hasColumn('padron_bajas_asignaciones', 'establecimiento_origen_id')
            && Schema::hasColumn('padron_bajas_asignaciones', 'establecimiento_destino_id');
    }

    public function ultimas(PadronRevision $revision): Collection
    {
        return $this->instalado()
            ? DB::table('padron_bajas_asignaciones')->where('padron_revision_id', $revision->id)
                ->when($this->trasladoInstalado(), fn ($q) => $q->where('tipo', 'ausencia'))
                ->orderBy('id')->get()->keyBy('rut')
            : collect();
    }

    /** Reutiliza las filas y el snapshot acotado del diagnóstico, sin otra lectura de asignaciones. */
    public function evaluar(PadronRevision $revision, Collection $filas, array $estados, array $asignaciones): array
    {
        if (! $this->instalado() || ! $revision->anio || $revision->errores || $filas->whereNotNull('fila_excel')->isEmpty()) {
            return [];
        }
        $entrantes = [];
        $ausentes = [];
        foreach ($filas as $fila) {
            $rut = PadronConciliador::rut($fila->rut);
            if ($fila->fila_excel !== null || ($estados[$fila->id] ?? '') === 'conservada') {
                // Figurar en el archivo impide presumir retiro completo.
                $entrantes[$rut] = true;
            } else {
                $ausentes[$rut][] = $fila;
            }
        }
        $continuidades = $this->continuidadesReemplazo($revision, $filas, array_intersect_key($entrantes, $ausentes));
        $ausentes = array_diff_key($ausentes, array_diff_key($entrantes, $continuidades));
        unset($ausentes['']);
        if (! $ausentes) {
            return [];
        }
        $ids = [];
        foreach ($ausentes as $rows) {
            foreach ($rows as $fila) { $ids[] = $fila->personal_id; }
        }
        $ids = array_unique(array_filter([...$ids, ...array_column($asignaciones, 'reemplazos_personal_id')]));
        $columnas = ['id', 'rut', 'establecimiento_id', 'anio'];
        if (Schema::hasColumn('reemplazos_personal', 'vigente')) { $columnas[] = 'vigente'; }
        $personal = DB::table('reemplazos_personal')->whereIn('id', $ids)->get($columnas)->keyBy('id');
        $porRut = [];
        $incompatibles = [];
        foreach ($asignaciones as $a) {
            $id = $a['reemplazos_personal_id'] ?? null;
            $normalizado = PadronConciliador::rut($a['docente_rut_normalizado'] ?? '');
            $literal = PadronConciliador::rut($a['docente_rut'] ?? '');
            $contractual = PadronConciliador::rut($personal[$id]->rut ?? '');
            $rut = $normalizado ?: ($literal ?: $contractual);
            $identidades = array_unique(array_filter([$normalizado, $literal, $contractual]));
            if (count($identidades) > 1 || ($id && ! isset($personal[$id]))) {
                foreach ($identidades as $identidad) { $incompatibles[$identidad] = true; }
            }
            if (isset($ausentes[$rut])) { $porRut[$rut][] = $a; }
        }
        $ultimas = $this->ultimas($revision);
        $out = [];
        foreach ($porRut as $rut => $rows) {
            $bajas = [];
            $establecimientos = [];
            $elegible = ! isset($incompatibles[$rut]);
            foreach ($ausentes[$rut] as $fila) {
                $p = $personal[$fila->personal_id] ?? null;
                $elegible = $elegible && $p && PadronConciliador::rut($p->rut) === (string) $rut
                    && (int) $p->anio === (int) $revision->anio
                    && in_array($fila->accion, ['baja_propuesta', 'ausencia_por_revisar'], true)
                    && in_array($estados[$fila->id] ?? '', ['resuelta', 'propuesta_automatica'], true);
                $bajas[] = (int) $fila->personal_id;
                if ($p) { $establecimientos[(int) $p->establecimiento_id] = true; }
            }
            $huellas = [];
            $horas = 0;
            foreach ($rows as $a) {
                $id = $a['reemplazos_personal_id'] ?? null;
                $elegible = $elegible && (int) $a['anio'] === (int) $revision->anio
                    && isset($establecimientos[(int) $a['establecimiento_id']])
                    && (! $id || (in_array((int) $id, $bajas, true)
                        && (int) $personal[$id]->establecimiento_id === (int) $a['establecimiento_id']))
                    && is_numeric($a['horas_contrato']) && (float) $a['horas_contrato'] >= 0;
                $huellas[(int) $a['id']] = $a['_huella'];
                $horas += (float) $a['horas_contrato'];
            }
            sort($bajas); ksort($huellas);
            $alcance = ['bajas' => array_values(array_unique($bajas)), 'asignaciones' => $huellas, 'anio' => (int) $revision->anio];
            // No altera huellas de bajas completas ya confirmadas. La excepción
            // vincula la autorización a las nuevas líneas de reemplazo resueltas.
            if (isset($continuidades[$rut])) { $alcance['continuidad_reemplazo'] = $continuidades[$rut]; }
            $hash = hash('sha256', json_encode([$revision->id, $revision->base_hash, (string) $rut, $alcance], JSON_THROW_ON_ERROR));
            $ultima = $ultimas->get($rut);
            $confirmada = $elegible && $ultima && $ultima->confirmada && hash_equals($hash, $ultima->alcance_hash);
            $out[$rut] = ['rut' => (string) $rut, 'elegible' => (bool) $elegible, 'confirmada' => (bool) $confirmada,
                'continuidad_reemplazo' => isset($continuidades[$rut]),
                'autorizada' => (bool) ($ultima && $ultima->confirmada),
                'alcance' => $alcance, 'alcance_hash' => $hash, 'ultima_id' => (int) ($ultima->id ?? 0),
                'horas' => round($horas, 2), 'cantidad' => count($rows), 'establecimientos' => count($establecimientos),
                'justificacion' => $ultima->justificacion ?? null, 'usuario_id' => $ultima->usuario_id ?? null,
                'desactualizada' => (bool) ($ultima && $ultima->confirmada && ! $confirmada)];
        }
        return $out;
    }

    /** Bajas anteriores con continuidad exclusivamente como nuevas líneas excluidas de Dotación. */
    private function continuidadesReemplazo(PadronRevision $revision, Collection $filas, array $candidatos): array
    {
        if (! $candidatos) { return []; }
        $resolucion = app(PadronResolucionService::class);
        if (! $resolucion->disponible()) { return []; }
        $filas = $filas->filter(fn ($fila) => isset($candidatos[PadronConciliador::rut($fila->rut)]));
        $decisiones = $resolucion->decisiones($revision);
        $selecciones = $resolucion->selecciones($filas, $decisiones);
        $establecimientos = DB::table('establecimientos')->pluck('id', 'rbd');
        $out = $invalidos = [];
        foreach ($resolucion->entrantes($revision, $filas, $decisiones) as $fila) {
            $rut = PadronConciliador::rut($fila->rut);
            $data = $fila->datos;
            // No confundir pendientes con nuevas líneas, ni liberar un ID que
            // continúa seleccionado. Conservaciones, regulares, errores y
            // reemplazos omitidos requieren resolver otro flujo explícito.
            if ($fila->accion === PadronReemplazosVigentes::OMITIDO || $fila->accion === 'error'
                || ! array_key_exists($fila->id, $selecciones) || $selecciones[$fila->id] !== null
                || $resolucion->tipoPropuesto($fila) !== 'reemplazo_suplencia'
                || PadronConciliador::rut($data['rut'] ?? '') !== $rut
                || (int) ($data['anio'] ?? 0) !== (int) $revision->anio
                || (int) ($data['mes'] ?? 0) !== (int) $revision->mes
                || ! isset($establecimientos[$data['rbd'] ?? 0])
                || ! is_numeric($data['jornada'] ?? null) || (float) $data['jornada'] <= 0) {
                $invalidos[$rut] = true;
                continue;
            }
            $out[$rut][$fila->id] = ['fila_excel' => $fila->fila_excel,
                'huella' => hash('sha256', json_encode($data, JSON_THROW_ON_ERROR))];
        }
        foreach ($out as &$propuestas) { ksort($propuestas); }
        unset($propuestas);
        return array_diff_key($out, $invalidos);
    }

    /**
     * Detecta asignaciones que permanecen activas en el RBD anterior mientras
     * el RUT fue resuelto en otro RBD del padrón, incluso sin ID en la asignación.
     * Si existe un vínculo contractual explícito, debe conservarse en el destino. La decisión
     * solo prepara una liberación diferida; nunca modifica Dotación aquí.
     */
    public function evaluarTraslados(PadronRevision $revision, Collection $filas, array $estados, array $asignaciones, ?Collection $decisiones = null): array
    {
        if (! $this->trasladoInstalado() || ! $revision->anio || $revision->errores || $filas->whereNotNull('fila_excel')->isEmpty()) {
            return [];
        }

        $resolucion = app(PadronResolucionService::class);
        $decisiones ??= $resolucion->decisiones($revision);
        $selecciones = $resolucion->resumen($filas, $decisiones)['selecciones'];
        $establecimientos = DB::table('establecimientos')->pluck('id', 'rbd');
        $destinos = [];
        $porRutDestino = [];
        $pendientes = [];
        foreach ($filas as $fila) {
            if (in_array($estados[$fila->id] ?? '', ['pendiente', 'error'], true)) {
                $pendientes[PadronConciliador::rut($fila->rut)] = true;
            }
        }
        $invalidos = [];
        foreach ($resolucion->entrantes($revision, $filas, $decisiones) as $fila) {
            if ($fila->accion === PadronReemplazosVigentes::OMITIDO || ! array_key_exists($fila->id, $selecciones)) {
                continue;
            }
            $id = $selecciones[$fila->id];
            $rut = PadronConciliador::rut($fila->rut);
            $destino = (int) ($establecimientos[$fila->datos['rbd'] ?? 0] ?? 0);
            if (! $destino || $resolucion->tipoPropuesto($fila) !== 'regular'
                || (int) ($fila->datos['anio'] ?? 0) !== (int) $revision->anio
                || (int) ($fila->datos['mes'] ?? 0) !== (int) $revision->mes
                || ! is_numeric($fila->datos['jornada'] ?? null) || (float) $fila->datos['jornada'] <= 0) {
                $invalidos[$rut] = true;
                continue;
            }
            $propuesta = ['rut' => $rut, 'establecimiento_id' => $destino, 'personal_id' => $id === null ? null : (int) $id,
                'huella' => hash('sha256', json_encode($fila->datos, JSON_THROW_ON_ERROR))];
            $porRutDestino[$rut][$destino][$fila->id] = $propuesta;
            if ($id !== null) { $destinos[(int) $id][] = $propuesta; }
        }

        $idsAsignaciones = array_values(array_unique(array_filter(array_map(
            fn (array $asignacion) => (int) ($asignacion['reemplazos_personal_id'] ?? 0), $asignaciones,
        ))));
        $idsAsignaciones = array_unique([...$idsAsignaciones, ...array_keys($destinos)]);
        $rutsContractuales = $idsAsignaciones
            ? DB::table('reemplazos_personal')->whereIn('id', $idsAsignaciones)->pluck('rut', 'id')->map(fn ($rut) => PadronConciliador::rut($rut))
            : collect();
        $porGrupo = [];
        foreach ($asignaciones as $asignacion) {
            $id = (int) ($asignacion['reemplazos_personal_id'] ?? 0);
            $normalizado = PadronConciliador::rut($asignacion['docente_rut_normalizado'] ?? null);
            $literal = PadronConciliador::rut($asignacion['docente_rut'] ?? null);
            $contractual = PadronConciliador::rut($rutsContractuales->get($id));
            $identidades = array_values(array_unique(array_filter([$normalizado, $literal, $contractual])));
            $rut = $normalizado ?: ($literal ?: $contractual);
            if ($rut === '') {
                continue;
            }
            $asignacion['_traslado_rut_incompatible'] = count($identidades) > 1 || ($id > 0 && ! $rutsContractuales->has($id));
            $porGrupo[$rut.'|'.(int) $asignacion['establecimiento_id']][] = $asignacion;
        }

        $ultimas = DB::table('padron_bajas_asignaciones')
            ->where('padron_revision_id', $revision->id)->where('tipo', 'traslado')->orderBy('id')->get()
            ->keyBy(fn ($row) => $row->rut.'|'.$row->establecimiento_origen_id.'|'.$row->establecimiento_destino_id);
        $out = [];
        foreach ($porGrupo as $key => $rows) {
            [$rut, $origen] = explode('|', $key, 2);
            $opciones = $porRutDestino[$rut] ?? [];
            // Nunca inferir retiro de un origen que aún tenga contrato propuesto,
            // ni escoger entre varios establecimientos de destino.
            if (count($opciones) !== 1 || isset($opciones[(int) $origen])) { continue; }
            $destino = (int) array_key_first($opciones);
            $propuestas = $opciones[$destino];
            $motivos = [];
            if (isset($pendientes[$rut])) { $motivos[] = 'Resuelva todas las correspondencias y ausencias pendientes de este RUT antes de confirmar el traslado.'; }
            if (isset($invalidos[$rut])) { $motivos[] = 'El destino debe contener contratos regulares con jornada positiva y período válido; revise las filas del RUT.'; }
            foreach ($propuestas as $propuesta) {
                $id = $propuesta['personal_id'];
                if ($id !== null && (count($destinos[$id]) !== 1 || $rutsContractuales->get($id) !== $rut)) {
                    $motivos[] = 'El ID de destino debe ser único y corresponder al mismo RUT.';
                }
            }
            $huellas = [];
            $personalIds = [];
            foreach ($rows as $asignacion) {
                $id = (int) ($asignacion['reemplazos_personal_id'] ?? 0);
                $candidatosDestino = $destinos[$id] ?? [];
                if ($asignacion['_traslado_rut_incompatible'] || (int) $asignacion['anio'] !== (int) $revision->anio
                    || ! is_numeric($asignacion['horas_contrato'] ?? null) || (float) $asignacion['horas_contrato'] < 0) {
                    $motivos[] = 'Revise la identidad, el año y las horas de las asignaciones de origen.';
                }
                if ($id > 0 && (count($candidatosDestino) !== 1 || $candidatosDestino[0]['rut'] !== $rut
                    || $candidatosDestino[0]['establecimiento_id'] !== $destino)) {
                    $motivos[] = 'La asignación conserva un vínculo al ID '.$id.'. Seleccione ese ID en su fila de destino; darlo de baja y crear una línea nueva no sustituye el vínculo histórico.';
                }
                if (! empty($asignacion['_huella'])) {
                    $huellas[(int) $asignacion['id']] = $asignacion['_huella'];
                }
                if ($id > 0) {
                    $personalIds[] = $id;
                }
            }
            if (count($huellas) !== count($rows)) { $motivos[] = 'Falta la huella de una asignación; vuelva a revisar el diagnóstico.'; }
            $elegible = ! $motivos;
            ksort($huellas);
            ksort($propuestas);
            sort($personalIds);
            $alcance = ['asignaciones' => $huellas, 'personal_ids' => array_values(array_unique($personalIds)),
                'origen' => (int) $origen, 'destino' => $destino, 'anio' => (int) $revision->anio,
                'propuestas_destino' => $propuestas];
            $hash = hash('sha256', json_encode([$revision->id, $revision->base_hash, $rut, $alcance], JSON_THROW_ON_ERROR));
            $clave = $rut.'|'.$origen.'|'.$destino;
            $ultima = $ultimas->get($clave);
            $confirmada = (bool) ($elegible && $ultima && $ultima->confirmada && hash_equals($hash, $ultima->alcance_hash));
            $out[$rut.'|'.$origen] = ['rut' => $rut, 'elegible' => $elegible, 'confirmada' => $confirmada,
                'motivos' => array_values(array_unique($motivos)),
                'nuevas_lineas' => count(array_filter($propuestas, fn ($p) => $p['personal_id'] === null)),
                'autorizada' => (bool) ($ultima && $ultima->confirmada), 'alcance' => $alcance,
                'alcance_hash' => $hash, 'ultima_id' => (int) ($ultima->id ?? 0), 'destino' => $destino,
                'destino_rbd' => $establecimientos->search($destino),
                'cantidad' => count($rows), 'horas' => round(array_sum(array_map(fn ($a) => (float) ($a['horas_contrato'] ?? 0), $rows)), 2),
                'justificacion' => $ultima->justificacion ?? null, 'usuario_id' => $ultima->usuario_id ?? null,
                'desactualizada' => (bool) ($ultima && $ultima->confirmada && ! $confirmada)];
        }
        return $out;
    }

    public function registrar(PadronRevision $revision, string $rut, string $hash, int $anterior, string $motivo, int $usuario, bool $confirmar): void
    {
        $rut = PadronConciliador::rut($rut);
        $motivo = trim($motivo);
        Validator::make(compact('rut', 'hash', 'anterior', 'motivo', 'usuario'), [
            'rut' => ['required', 'string', 'max:32'], 'hash' => ['required', 'regex:/^[a-f0-9]{64}$/'],
            'anterior' => ['integer', 'min:0'], 'motivo' => ['required', 'string', 'min:10', 'max:2000'],
            'usuario' => ['integer', 'min:1'],
        ])->validate();
        if (! $this->instalado()) { $this->fail('Instale la migración de bajas y liberación de asignaciones con PHP 8.3.'); }
        DB::transaction(function () use ($revision, $rut, $hash, $anterior, $motivo, $usuario, $confirmar): void {
            $revision = PadronRevision::whereKey($revision->id)->lockForUpdate()->firstOrFail();
            if ($revision->aplicada_at || $revision->errores || app(PadronRevisionService::class)->stale($revision)) {
                $this->fail('La revisión está cerrada, contiene errores o cambió el padrón base.');
            }
            $ultima = $this->ultimas($revision)->get($rut);
            if ((int) ($ultima->id ?? 0) !== $anterior) {
                // Solo reintentos idénticos del último formulario son idempotentes.
                if ($ultima && (bool) $ultima->confirmada === $confirmar && $ultima->alcance_hash === $hash
                    && $ultima->justificacion === $motivo && (int) $ultima->usuario_id === $usuario) { return; }
                $this->fail('Otra decisión de baja fue registrada. Recargue antes de confirmar o retirar la autorización.');
            }
            $candidato = app(PadronConflictosAsignacionService::class)->analizar($revision)['bajas_asignaciones'][$rut] ?? null;
            if ($confirmar && (! $candidato || ! $candidato['elegible'] || ! hash_equals($candidato['alcance_hash'], $hash))) {
                $this->fail('Las bajas no son elegibles para liberar asignaciones o cambió el alcance. Recargue y revise las correspondencias y propuestas del RUT.');
            }
            if (! $confirmar && (! $ultima || ! $ultima->confirmada)) { $this->fail('No hay una liberación confirmada que retirar.'); }
            DB::table('padron_bajas_asignaciones')->insert([
                'padron_revision_id' => $revision->id, 'rut' => $rut, 'confirmada' => $confirmar,
                'alcance_hash' => $confirmar ? $hash : $ultima->alcance_hash,
                'alcance' => $confirmar ? json_encode($candidato['alcance'], JSON_THROW_ON_ERROR) : $ultima->alcance,
                'justificacion' => $motivo, 'usuario_id' => $usuario, 'created_at' => now(),
            ]);
        });
    }

    public function registrarTraslado(PadronRevision $revision, string $rut, int $origen, int $destino, string $hash, int $anterior, string $motivo, int $usuario, bool $confirmar): void
    {
        $rut = PadronConciliador::rut($rut);
        $motivo = trim($motivo);
        Validator::make(compact('rut', 'origen', 'destino', 'hash', 'anterior', 'motivo', 'usuario'), [
            'rut' => ['required', 'string', 'max:32'], 'origen' => ['required', 'integer', 'min:1'], 'destino' => ['required', 'integer', 'min:1'],
            'hash' => ['required', 'regex:/^[a-f0-9]{64}$/'], 'anterior' => ['integer', 'min:0'],
            'motivo' => ['required', 'string', 'min:10', 'max:2000'], 'usuario' => ['integer', 'min:1'],
        ])->validate();
        if (! $this->trasladoInstalado()) { $this->fail('Instale la migración de traslados y liberación de asignaciones con PHP 8.3.'); }
        DB::transaction(function () use ($revision, $rut, $origen, $destino, $hash, $anterior, $motivo, $usuario, $confirmar): void {
            $revision = PadronRevision::whereKey($revision->id)->lockForUpdate()->firstOrFail();
            if ($revision->aplicada_at || $revision->errores || app(PadronRevisionService::class)->stale($revision)) {
                $this->fail('La revisión está cerrada, contiene errores o cambió el padrón base.');
            }
            $ultima = DB::table('padron_bajas_asignaciones')->where('padron_revision_id', $revision->id)->where('tipo', 'traslado')
                ->where('rut', $rut)->where('establecimiento_origen_id', $origen)->where('establecimiento_destino_id', $destino)->latest('id')->first();
            if ((int) ($ultima->id ?? 0) !== $anterior) {
                if ($ultima && (bool) $ultima->confirmada === $confirmar && $ultima->alcance_hash === $hash && $ultima->justificacion === $motivo && (int) $ultima->usuario_id === $usuario) { return; }
                $this->fail('Otra decisión de traslado fue registrada. Recargue antes de confirmar o retirar la autorización.');
            }
            $candidato = app(PadronConflictosAsignacionService::class)->analizar($revision)['traslados_asignaciones'][$rut.'|'.$origen] ?? null;
            if ($confirmar && (! $candidato || ! $candidato['elegible'] || (int) $candidato['destino'] !== $destino || ! hash_equals($candidato['alcance_hash'], $hash))) {
                $this->fail('El traslado no es elegible o sus asignaciones cambiaron. Recargue y revise el alcance.');
            }
            if (! $confirmar && (! $ultima || ! $ultima->confirmada)) { $this->fail('No hay una liberación de traslado confirmada que retirar.'); }
            DB::table('padron_bajas_asignaciones')->insert([
                'padron_revision_id' => $revision->id, 'rut' => $rut, 'tipo' => 'traslado',
                'establecimiento_origen_id' => $origen, 'establecimiento_destino_id' => $destino,
                'confirmada' => $confirmar, 'alcance_hash' => $confirmar ? $hash : $ultima->alcance_hash,
                'alcance' => $confirmar ? json_encode($candidato['alcance'], JSON_THROW_ON_ERROR) : $ultima->alcance,
                'justificacion' => $motivo, 'usuario_id' => $usuario, 'created_at' => now(),
            ]);
        });
    }

    /** Solo después de revalidar el plan y bajo los bloqueos del escritor. */
    public function aplicar(PadronRevision $revision, array $bajas, array $confirmaciones, int $usuario): void
    {
        if (! $confirmaciones) { return; }
        if (DB::transactionLevel() === 0 || ! $this->instalado()) { $this->fail('La liberación requiere la transacción de aplicación.'); }
        $columnas = array_flip(Schema::getColumnListing('dotacion_docente_asignaciones'));
        foreach ($confirmaciones as $confirmacion) {
            if (! $confirmacion['confirmada']) { continue; }
            if (array_diff($confirmacion['alcance']['bajas'], $bajas)) { $this->fail('La liberación no corresponde a las bajas del plan.'); }
            foreach ($confirmacion['alcance']['asignaciones'] as $id => $huella) {
                $antes = DB::table('dotacion_docente_asignaciones')->where('id', $id)->lockForUpdate()->first();
                if (! $antes || $antes->estado !== 'activa' || (int) $antes->anio !== (int) $revision->anio
                    || ! hash_equals($huella, hash('sha256', json_encode($antes, JSON_THROW_ON_ERROR)))) {
                    $this->fail('Una asignación cambió desde la confirmación de baja. No se aplicaron cambios.');
                }
                DB::table('dotacion_docente_asignaciones')->where('id', $id)->update(array_intersect_key([
                    'estado' => 'inactiva', 'updated_by' => $usuario, 'updated_at' => now()->toDateTimeString(),
                ], $columnas));
                DB::table('padron_asignacion_cambios')->insert([
                    'padron_revision_id' => $revision->id, 'baja_asignaciones_id' => $confirmacion['ultima_id'],
                    'asignacion_id' => $id, 'antes' => json_encode($antes, JSON_THROW_ON_ERROR),
                    'despues' => json_encode(DB::table('dotacion_docente_asignaciones')->find($id), JSON_THROW_ON_ERROR),
                    'usuario_id' => $usuario, 'created_at' => now(),
                ]);
            }
        }
    }

    /** Inactiva solo asignaciones del origen ya confirmadas como traslado. */
    public function aplicarTraslados(PadronRevision $revision, array $confirmaciones, int $usuario): void
    {
        if (! $confirmaciones) { return; }
        if (DB::transactionLevel() === 0 || ! $this->trasladoInstalado()) { $this->fail('La liberación por traslado requiere la transacción de aplicación.'); }
        $columnas = array_flip(Schema::getColumnListing('dotacion_docente_asignaciones'));
        foreach ($confirmaciones as $confirmacion) {
            if (! ($confirmacion['confirmada'] ?? false)) { continue; }
            foreach ($confirmacion['alcance']['asignaciones'] as $id => $huella) {
                $antes = DB::table('dotacion_docente_asignaciones')->where('id', $id)->lockForUpdate()->first();
                if (! $antes || $antes->estado !== 'activa' || (int) $antes->anio !== (int) $revision->anio
                    || (int) $antes->establecimiento_id !== (int) $confirmacion['alcance']['origen']
                    || ! hash_equals($huella, hash('sha256', json_encode($antes, JSON_THROW_ON_ERROR)))) {
                    $this->fail('Una asignación del traslado cambió desde la confirmación. No se aplicaron cambios.');
                }
                DB::table('dotacion_docente_asignaciones')->where('id', $id)->update(array_intersect_key([
                    'estado' => 'inactiva', 'updated_by' => $usuario, 'updated_at' => now()->toDateTimeString(),
                ], $columnas));
                DB::table('padron_asignacion_cambios')->insert([
                    'padron_revision_id' => $revision->id, 'baja_asignaciones_id' => $confirmacion['ultima_id'],
                    'asignacion_id' => $id, 'antes' => json_encode($antes, JSON_THROW_ON_ERROR),
                    'despues' => json_encode(DB::table('dotacion_docente_asignaciones')->find($id), JSON_THROW_ON_ERROR),
                    'usuario_id' => $usuario, 'created_at' => now(),
                ]);
            }
        }
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['revision' => $message]);
    }
}
