<?php

namespace App\Services\Padron;

use App\Models\PadronRevision;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class PadronAplicacionService
{
    // Primera entrega: conservar el trabajo de aplicación, pero no habilitarlo
    // solo por instalar tablas. Falta validar lectores de documentos históricos
    // y cobertura con prioridad de Declaración de Sostenedores.
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
        return DB::table('padron_revision_decisiones')->where('padron_revision_id', $revision->id)
            ->orderBy('id')->get()->keyBy('padron_revision_fila_id');
    }

    public function resolver(PadronRevision $revision, int $filaId, ?int $personalId, string $motivo, int $usuario): void
    {
        $this->assertDisponible();
        DB::transaction(function () use ($revision, $filaId, $personalId, $motivo, $usuario): void {
            $revision = PadronRevision::whereKey($revision->id)->lockForUpdate()->firstOrFail();
            $this->assertEditable($revision);
            $fila = $revision->filas()->findOrFail($filaId);
            if (! in_array($fila->accion, ['revision_manual', 'ausencia_por_revisar'], true)
                || mb_strlen(trim($motivo)) < 10 || mb_strlen($motivo) > 2000) {
                $this->fail('Solo se resuelven filas ambiguas, con justificación de 10 a 2.000 caracteres.');
            }
            if ($fila->fila_excel && PadronConciliador::tipo($fila->datos) === 'por_clasificar') {
                $this->fail('Corrija el tipo de contrato desconocido en el Excel y vuelva a analizar.');
            }
            $candidatos = collect($fila->candidatos)->pluck('id')->map(fn ($id) => (int) $id)->all();
            if ($personalId !== null && (! $fila->fila_excel || ! in_array($personalId, $candidatos, true))) {
                $this->fail('El ID seleccionado no es un candidato válido para esta fila.');
            }
            // Las decisiones son anexadas, nunca sobrescritas: conserva auditoría.
            DB::table('padron_revision_decisiones')->insert([
                'padron_revision_id' => $revision->id, 'padron_revision_fila_id' => $filaId,
                'personal_id' => $personalId, 'justificacion' => trim($motivo), 'resuelta_por' => $usuario,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        });
    }

    /** Calcula bloqueos en servidor, tanto para la pantalla como para aplicar. */
    public function plan(PadronRevision $revision): array
    {
        $errores = $revision->errores ?? [];
        $decisiones = $this->decisiones($revision);
        $filas = $revision->filas()->orderBy('id')->get();
        $destinos = [];
        $usados = [];
        foreach ($filas->whereNotNull('fila_excel') as $fila) {
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
        }
        $autorizaciones = DB::table('padron_revision_autorizaciones')->where('padron_revision_id', $revision->id)->get()->keyBy('rut');
        foreach ($revision->excesos as $rut => $exceso) {
            if (! $autorizaciones->has($rut) || (float) $autorizaciones[$rut]->jornada_total !== (float) $exceso['total']) {
                $errores[] = 'RUT '.$rut.': faltan autorización y justificación para '.$exceso['total'].' horas.';
            }
        }
        // No modifica asignaciones. Cualquier pérdida de cobertura exige resolver
        // primero en Dotación y generar otra revisión con la base actualizada.
        $establecimientos = DB::table('establecimientos')->pluck('id', 'rbd');
        $cobertura = [];
        $destinoPorId = [];
        foreach ($destinos as $destino) {
            $data = $destino['fila']->datos;
            $estId = $establecimientos[$data['rbd'] ?? 0] ?? 0;
            if (PadronConciliador::tipo($data) === 'regular') {
                $key = $data['rut'].'|'.$estId;
                $cobertura[$key] = ($cobertura[$key] ?? 0) + ($data['jornada'] ?? 0);
            }
            if ($destino['id']) {
                $destinoPorId[$destino['id']] = $estId;
            }
        }
        $asignadas = [];
        $asignaciones = Schema::hasTable('dotacion_docente_asignaciones')
            ? DB::table('dotacion_docente_asignaciones')->where('anio', $revision->anio)->where('estado', 'activa')->get()->map(fn ($a) => (array) $a)
            : collect();
        foreach ($asignaciones as $a) {
            $id = $a['reemplazos_personal_id'] ?? null;
            if ($id && (($destinoPorId[$id] ?? null) != $a['establecimiento_id'])) {
                $errores[] = 'Asignación #'.$a['id'].': su ID contractual se trasladaría o quedaría fuera del padrón. Resuelva en Dotación antes de aplicar.';
            }
            $rut = PadronConciliador::rut(($a['docente_rut_normalizado'] ?? null) ?: ($a['docente_rut'] ?? ''));
            $key = $rut.'|'.$a['establecimiento_id'];
            $asignadas[$key] = ($asignadas[$key] ?? 0) + (float) ($a['horas_contrato'] ?? 0);
        }
        foreach ($asignadas as $key => $horas) {
            if ($horas > ($cobertura[$key] ?? 0) + 0.01) {
                $errores[] = 'RUT/establecimiento '.$key.': '.$horas.' h asignadas superan la cobertura regular del archivo. Resuelva las asignaciones y vuelva a analizar.';
            }
        }
        return ['destinos' => $destinos, 'errores' => array_values(array_unique($errores))];
    }

    public function aplicar(PadronRevision $revision, int $usuario): PadronRevision
    {
        $this->assertDisponible();
        $columns = array_flip(Schema::getColumnListing('reemplazos_personal'));
        return DB::transaction(function () use ($revision, $usuario, $columns): PadronRevision {
            // Un único escritor de cargas completas, incluso para revisiones distintas.
            DB::table('padron_aplicacion_control')->where('id', 1)->lockForUpdate()->firstOrFail();
            $revision = PadronRevision::whereKey($revision->id)->lockForUpdate()->firstOrFail();
            if ($revision->aplicada_at) {
                return $revision; // Reintentos no duplican registros ni auditoría.
            }
            $anteriores = DB::table('reemplazos_personal')->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            if (Schema::hasTable('dotacion_docente_asignaciones')) {
                DB::table('dotacion_docente_asignaciones')->orderBy('id')->lockForUpdate()->get(['id']);
            }
            $this->assertEditable($revision);
            $plan = $this->plan($revision);
            if ($plan['errores']) {
                throw ValidationException::withMessages(['revision' => $plan['errores']]);
            }
            $establecimientos = DB::table('establecimientos')->pluck('id', 'rbd');
            $ruts = [];
            foreach ($anteriores as $old) {
                $ruts[PadronConciliador::rut($old->rut)] ??= $old->rut;
            }
            $vigentes = [];
            foreach ($plan['destinos'] as $destino) {
                $fila = $destino['fila'];
                $before = $destino['id'] ? (array) $anteriores[$destino['id']] : null;
                $data = $fila->datos;
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
                $this->auditar($revision, $id, $before, $before ? 'actualizacion' : 'incorporacion', $usuario);
            }
            // Conserva todas las filas históricas y sus FK. Desactiva las líneas
            // no seleccionadas del año, evitando reapariciones de meses antiguos.
            foreach ($anteriores as $old) {
                if ((int) $old->anio !== (int) $revision->anio || isset($vigentes[$old->id]) || ! $old->vigente) {
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
            $this->fail('La aplicación definitiva y la resolución manual no están habilitadas en esta etapa. Solo puede previsualizar y registrar autorizaciones; instalar las migraciones no habilita cambios al personal.');
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
