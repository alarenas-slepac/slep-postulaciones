<?php

namespace App\Services\Padron;

use App\Models\PadronRevision;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class PadronAplicacionService
{
    // Mantener cerrado hasta validar concurrencia real en MySQL y terminar el
    // inventario de consumidores históricos. Las pruebas no habilitan producción.
    private const APLICACION_HABILITADA = false;

    public function __construct(private PadronRevisionService $revisiones) {}

    public function disponible(): bool
    {
        return self::APLICACION_HABILITADA
            && Schema::hasTable('padron_aplicacion_control')
            && Schema::hasTable('padron_revision_decisiones')
            && Schema::hasTable('padron_personal_cambios')
            && Schema::hasColumn('padron_revisiones', 'aplicada_at')
            && Schema::hasColumn('padron_revisiones', 'aplicada_por');
    }

    public function decisiones(PadronRevision $revision)
    {
        return app(PadronResolucionService::class)->decisiones($revision);
    }

    public function resolver(PadronRevision $revision, int $filaId, ?int $personalId, string $motivo, int $usuario, int $decisionAnterior = 0): void
    {
        app(PadronResolucionService::class)->resolver($revision, $filaId, $personalId, $motivo, $usuario, $decisionAnterior);
    }

    /** Calcula bloqueos en servidor, tanto para la pantalla como para aplicar. */
    public function plan(PadronRevision $revision): array
    {
        $confirmacionInicial = $this->confirmacionHash($revision);
        $errores = $revision->errores ?? [];
        $decisiones = app(PadronResolucionService::class)->disponible() ? $this->decisiones($revision) : collect();
        $filas = $revision->filas()->orderBy('id')->get();
        $destinos = [];
        $bajas = [];
        $usados = [];
        if (! $revision->anio || ! $revision->mes || $filas->whereNotNull('fila_excel')->isEmpty()) {
            $errores[] = 'No se puede aplicar un padrón vacío o sin período válido.';
        }
        $entrantes = $filas->whereNotNull('fila_excel')->values();
        $vigenciaReemplazos = (new PadronReemplazosVigentes)->evaluar($entrantes->map(fn ($fila) => $fila->toArray())->all());
        foreach ($entrantes as $index => $fila) {
            if ((int) ($fila->datos['anio'] ?? 0) !== (int) $revision->anio || (int) ($fila->datos['mes'] ?? 0) !== (int) $revision->mes) {
                $errores[] = 'Fila '.$fila->fila_excel.': el período no coincide con la revisión.';
            }
            if (($fila->accion === PadronReemplazosVigentes::OMITIDO) !== isset($vigenciaReemplazos['omitidas'][$index])) {
                $errores[] = 'Fila '.$fila->fila_excel.': la selección de reemplazos no coincide con las fechas y jornadas. Analice nuevamente el archivo.';
            }
            if (isset($vigenciaReemplazos['bloqueos'][$index])) {
                $errores[] = 'Fila '.$fila->fila_excel.': '.$vigenciaReemplazos['bloqueos'][$index];
            }
            if ($fila->accion === PadronReemplazosVigentes::OMITIDO) {
                continue;
            }
            $id = $fila->personal_id;
            if ($fila->accion === 'revision_manual') {
                if (! $decisiones->has($fila->id)) {
                    $errores[] = 'Fila '.$fila->fila_excel.': seleccione un ID candidato o confirme nueva línea.';
                    continue;
                }
                $id = $decisiones[$fila->id]->personal_id;
            }
            if ($fila->accion === 'error' || PadronConciliador::tipo($fila->datos) === 'por_clasificar') {
                $errores[] = 'Fila '.$fila->fila_excel.': corrija los datos o el tipo de contrato en el archivo.';
            }
            if ($id !== null && isset($usados[$id])) {
                $errores[] = 'ID '.$id.': seleccionado en más de una fila; no se puede aplicar.';
            }
            if ($id !== null) {
                $usados[$id] = true;
            }
            $destinos[] = ['fila' => $fila, 'id' => $id === null ? null : (int) $id];
        }
        foreach ($filas->whereNull('fila_excel') as $fila) {
            if (! isset($usados[$fila->personal_id]) && $fila->accion === 'ausencia_por_revisar' && ! $decisiones->has($fila->id)) {
                $errores[] = 'ID '.$fila->personal_id.': confirme su baja o vincúlelo a una fila del archivo.';
            }
            if (! isset($usados[$fila->personal_id]) && ($fila->accion === 'baja_propuesta'
                || ($fila->accion === 'ausencia_por_revisar' && $decisiones->has($fila->id)))) {
                $bajas[] = (int) $fila->personal_id;
            }
        }
        $autorizaciones = DB::table('padron_revision_autorizaciones')->where('padron_revision_id', $revision->id)->get()->keyBy('rut');
        foreach ($revision->excesos as $rut => $exceso) {
            if (! $autorizaciones->has($rut) || (float) $autorizaciones[$rut]->jornada_total !== (float) $exceso['total']) {
                $errores[] = 'RUT '.$rut.': faltan autorización y justificación para '.$exceso['total'].' horas.';
            }
        }
        $conflictos = app(PadronConflictosAsignacionService::class)->analizar($revision);
        if (! $this->confirmacionVigente($revision, $confirmacionInicial)) {
            $errores[] = 'La revisión cambió mientras se calculaba el plan. Recargue antes de confirmar.';
        }
        return ['destinos' => $destinos, 'bajas' => array_values(array_unique($bajas)), 'conflictos' => $conflictos,
            'confirmacion_hash' => $confirmacionInicial,
            'errores' => array_values(array_unique(array_merge($errores, $conflictos['errores'])))];
    }

    private function confirmacionHash(PadronRevision $revision): string
    {
        $parts = [DB::table('padron_revisiones')->find($revision->id)];
        foreach (['padron_revision_filas', 'padron_revision_decisiones', 'padron_revision_autorizaciones'] as $table) {
            $parts[] = Schema::hasTable($table)
                ? DB::table($table)->where('padron_revision_id', $revision->id)->orderBy('id')->get()->all() : [];
        }
        return hash('sha256', json_encode($parts, JSON_THROW_ON_ERROR));
    }

    public function confirmacionVigente(PadronRevision $revision, string $hash): bool
    {
        return hash_equals($this->confirmacionHash($revision), $hash);
    }

    public function aplicar(PadronRevision $revision, int $usuario, ?string $confirmacionHash = null): PadronRevision
    {
        $this->assertDisponible();
        if (DB::transactionLevel() !== 0) {
            $this->fail('La aplicación debe iniciar su propia transacción, sin una lectura anterior abierta.');
        }
        if ($usuario <= 0 || ! preg_match('/^[a-f0-9]{64}$/', $confirmacionHash ?? '')) {
            $this->fail('Falta la confirmación vigente de la revisión. Recargue la pantalla.');
        }
        $columns = array_flip(Schema::getColumnListing('reemplazos_personal'));
        foreach (['vigente', 'fecha_antiguedad', 'row_hash', 'created_at', 'updated_at'] as $required) {
            if (! isset($columns[$required])) {
                $this->fail('Falta la columna requerida para la aplicación segura: '.$required.'.');
            }
        }
        return DB::transaction(function () use ($revision, $usuario, $columns, $confirmacionHash): PadronRevision {
            // Un único escritor de cargas completas, incluso para revisiones distintas.
            DB::table('padron_aplicacion_control')->where('id', 1)->lockForUpdate()->firstOrFail();
            $revision = PadronRevision::whereKey($revision->id)->lockForUpdate()->firstOrFail();
            if ($revision->aplicada_at) {
                return $revision; // Reintentos no duplican registros ni auditoría.
            }
            foreach (['padron_revision_filas', 'padron_revision_decisiones', 'padron_revision_autorizaciones'] as $table) {
                DB::table($table)->where('padron_revision_id', $revision->id)->orderBy('id')->lockForUpdate()->get(['id']);
            }
            $anteriores = DB::table('reemplazos_personal')->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            foreach (['establecimientos', 'dotacion_docente_asignaciones', 'declaracion_sostenedores', 'dotacion_docente_exclusiones',
                ...PadronHistorialService::DOCUMENTOS, 'reemplazos_personal_bloqueos'] as $table) {
                if (Schema::hasTable($table)) {
                    DB::table($table)->orderBy('id')->lockForUpdate()->get(['id']);
                }
            }
            $this->assertEditable($revision);
            $plan = $this->plan($revision);
            if (! hash_equals($plan['confirmacion_hash'], $confirmacionHash)) {
                $this->fail('Las filas, decisiones o autorizaciones cambiaron desde la confirmación. Revise nuevamente la pantalla.');
            }
            if ($plan['errores']) {
                throw ValidationException::withMessages(['revision' => $plan['errores']]);
            }
            foreach ($plan['destinos'] as $destino) {
                if ($destino['id'] !== null && (! isset($anteriores[$destino['id']])
                    || PadronConciliador::rut($anteriores[$destino['id']]->rut) !== PadronConciliador::rut($destino['fila']->rut))) {
                    $this->fail('El ID seleccionado no corresponde al RUT de la fila. Genere un nuevo análisis.');
                }
            }
            $idsAfectados = collect($plan['destinos'])->pluck('id')->filter()
                ->merge($plan['bajas'])
                ->unique()->values()->all();
            app(PadronHistorialService::class)->congelarReferencias($idsAfectados);
            $establecimientos = DB::table('establecimientos')->pluck('id', 'rbd');
            $ruts = [];
            foreach ($anteriores as $old) {
                $ruts[PadronConciliador::rut($old->rut)] ??= $old->rut;
            }
            $vigentes = [];
            foreach ($plan['destinos'] as $destino) {
                $fila = $destino['fila'];
                $before = $destino['id'] ? (array) $anteriores[$destino['id']] : null;
                // La carga nunca puede inyectar IDs, hashes ni campos internos.
                $data = array_intersect_key($fila->datos, array_flip([...PadronExcelReader::REQUIRED, 'tramo', 'fecha_antiguedad']));
                if (empty($data['fecha_antiguedad'])) {
                    unset($data['fecha_antiguedad']);
                }
                $data['rut'] = $before['rut'] ?? $ruts[$data['rut']] ?? $data['rut'];
                $data['establecimiento_id'] = $establecimientos[$data['rbd']];
                $data['vigente'] = true;
                $data['source_filename'] = $revision->archivo;
                $data['updated_at'] = now()->toDateTimeString();
                if ($before === null) {
                    $data['created_at'] = now()->toDateTimeString();
                    $data['created_by'] = $usuario;
                    $data['row_hash'] = hash('sha256', 'padron|'.$revision->id.'|'.$fila->id);
                }
                $data = array_intersect_key($data, $columns);
                if ($before !== null) {
                    DB::table('reemplazos_personal')->where('id', $destino['id'])->update($data);
                    $id = $destino['id'];
                } else {
                    $id = DB::table('reemplazos_personal')->insertGetId($data);
                }
                $vigentes[$id] = true;
                $this->auditar($revision, $id, $before, $before ? ($before['vigente'] ? 'actualizacion' : 'reactivacion') : 'incorporacion', $usuario);
            }
            // Solo bajas presentes y resueltas en la revisión. Nunca barrer todo
            // el año ni modificar versiones históricas no incluidas en el plan.
            foreach ($plan['bajas'] as $id) {
                $old = $anteriores[$id] ?? null;
                if (! $old) {
                    $this->fail('Una baja propuesta ya no existe. Genere un nuevo análisis.');
                }
                if (isset($vigentes[$old->id]) || ! $old->vigente) {
                    continue;
                }
                DB::table('reemplazos_personal')->where('id', $old->id)->update(array_intersect_key([
                    'vigente' => false, 'updated_at' => now()->toDateTimeString(),
                ], $columns));
                $this->auditar($revision, $old->id, (array) $old, 'desactivacion', $usuario);
            }
            $revision->forceFill(['aplicada_at' => now(), 'aplicada_por' => $usuario])->save();
            return $revision;
        }, 3);
    }

    private function auditar(PadronRevision $revision, int $id, ?array $antes, string $accion, int $usuario): void
    {
        DB::table('padron_personal_cambios')->insert([
            'padron_revision_id' => $revision->id, 'personal_id' => $id, 'accion' => $accion,
            'antes' => $antes === null ? null : json_encode($antes, JSON_THROW_ON_ERROR),
            'despues' => json_encode(DB::table('reemplazos_personal')->find($id), JSON_THROW_ON_ERROR),
            'usuario_id' => $usuario, 'created_at' => now(),
        ]);
    }

    private function assertDisponible(): void
    {
        if (! $this->disponible()) {
            $this->fail('La aplicación definitiva no está habilitada en esta etapa. Resolver coincidencias o registrar autorizaciones no modifica el personal.');
        }
    }

    private function assertEditable(PadronRevision $revision): void
    {
        if ($revision->aplicada_at || $this->revisiones->stale($revision)) {
            $this->fail('La revisión ya fue aplicada o la base cambió. Genere un nuevo análisis.');
        }
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['revision' => $message]);
    }
}
