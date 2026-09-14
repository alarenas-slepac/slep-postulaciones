<?php

namespace App\Services\Padron;

use App\Models\ReemplazoPersonal;
use App\Models\Establecimiento;
use App\Models\User;
use App\Support\Rut;
use App\Support\RutChile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PadronIndividualService
{
    public const CONTRATOS = ['PLANTA', 'CONTRATA', 'PLAZO FIJO', 'INDEFINIDO', 'REEMPLAZO'];
    public const CAMPOS = ['nombre', 'fecha_nacimiento', 'fecha_ingreso', 'fecha_termino', 'fecha_antiguedad',
        'tipocontrato', 'financiamiento', 'estatuto', 'escalafon', 'jornada', 'jornada_basica', 'jornada_media', 'bienios', 'tramo'];

    public function rut(string $valor): string
    {
        if (! preg_match('/^[0-9.kK\-\s]+$/', $valor)) {
            $this->fail('rut', 'Ingrese un RUT con dígito verificador válido.');
        }
        $rut = Rut::normalize($valor) ?? '';
        if (! preg_match('/^[1-9][0-9]{0,7}[0-9K]$/', $rut)
            || RutChile::dv((int) substr($rut, 0, -1)) !== substr($rut, -1)) {
            $this->fail('rut', 'El RUT o su dígito verificador no es válido. No se calcula ni corrige automáticamente.');
        }
        return $rut;
    }

    public function registros(string $rut): Collection
    {
        return ReemplazoPersonal::query()
            ->whereRaw("REPLACE(REPLACE(REPLACE(UPPER(TRIM(rut)), '.', ''), '-', ''), ' ', '') = ?", [$rut])
            ->orderBy('id')->get();
    }

    public function periodo(): int
    {
        // No adelantar el padrón completo al ingresar una sola persona.
        return app(PadronPeriodoService::class)->periodoMaximo();
    }

    public function huella(Collection $registros, int $periodo): string
    {
        return hash('sha256', $periodo.'|'.$registros->map(fn ($p) => $p->getRawOriginal())->toJson());
    }

    public function regular(?string $contrato): bool
    {
        $tipo = Str::upper(Str::ascii(trim((string) $contrato)));
        return (bool) preg_match('/^(PLANTA|CONTRATA|PLAZO FIJO|INDEFINIDO)(?:$|\s)/', $tipo)
            && ! str_contains($tipo, 'REEMPLAZ') && ! str_contains($tipo, 'SUPLEN');
    }

    public function editable(ReemplazoPersonal $p, int $periodo): bool
    {
        return $p->anio * 100 + $p->mes === $periodo && $this->regular($p->tipocontrato);
    }

    public function guardar(array $entrada, User $usuario): ReemplazoPersonal
    {
        abort_unless($usuario->hasRole('admin'), 403);
        if (! Schema::hasTable('padron_individual_cambios') || ! Schema::hasTable('padron_aplicacion_control')) {
            $this->fail('padron', 'Instale las migraciones de auditoría y control antes de gestionar registros individuales.');
        }

        return app(PadronEscrituraService::class)->ejecutar(function () use ($entrada, $usuario) {
            $data = Validator::make($entrada, [
                'rut' => ['required', 'string', 'max:20'],
                'personal_id' => ['nullable', 'integer', 'min:1'],
                'huella' => ['required', 'string', 'size:64'],
                'periodo' => ['required', 'integer'],
                'establecimiento_id' => ['required', 'integer', 'exists:establecimientos,id'],
                'nombre' => ['required', 'string', 'max:255'],
                'tipocontrato' => ['required', Rule::in(self::CONTRATOS)],
                'fecha_nacimiento' => ['nullable', 'date_format:Y-m-d', 'before:today'],
                'fecha_ingreso' => ['required', 'date_format:Y-m-d'],
                'fecha_termino' => ['nullable', 'required_if:tipocontrato,REEMPLAZO,PLAZO FIJO', 'date_format:Y-m-d', 'after_or_equal:fecha_ingreso'],
                'fecha_antiguedad' => ['nullable', 'date_format:Y-m-d'],
                'financiamiento' => ['required', 'string', 'max:255'],
                'estatuto' => ['required', 'string', 'max:255'],
                'escalafon' => ['required', 'string', 'max:255'],
                'jornada' => ['required', 'integer', 'min:1', 'max:168'],
                'jornada_basica' => ['required', 'integer', 'min:0', 'lte:jornada'],
                'jornada_media' => ['required', 'integer', 'min:0', 'lte:jornada'],
                'bienios' => ['nullable', 'integer', 'min:0', 'max:50'],
                'tramo' => ['nullable', 'string', 'max:100'],
                'justificacion' => ['required', 'string', 'min:10', 'max:2000'],
                'autorizar_exceso' => ['nullable', 'boolean'],
                'justificacion_exceso' => ['nullable', 'string', 'max:2000'],
                'confirmar_asignaciones' => ['nullable', 'boolean'],
                'liberar_asignaciones' => ['nullable', 'array', 'max:1000'],
                'liberar_asignaciones.*' => ['required', 'integer', 'min:1', 'distinct'],
                'huellas_asignaciones' => ['nullable', 'array', 'max:1000'],
                'huellas_asignaciones.*' => ['required', 'string', 'size:64'],
                'confirmar_liberacion' => ['nullable', 'boolean'],
                'justificacion_liberacion' => ['nullable', 'string', 'max:2000'],
            ])->validate();
            $rut = $this->rut($data['rut']);
            $registros = $this->registros($rut);
            $periodo = $this->periodo();
            if ($periodo < 200001 || $periodo !== (int) $data['periodo']
                || ! hash_equals($this->huella($registros, $periodo), $data['huella'])) {
                $this->fail('padron', 'El padrón o los contratos de este RUT cambiaron. Consulte nuevamente antes de guardar.');
            }
            if ((int) $data['jornada_basica'] + (int) $data['jornada_media'] > (int) $data['jornada']) {
                $this->fail('jornada', 'Jornada básica y media son componentes: su suma no puede superar la jornada total.');
            }
            $id = (int) ($data['personal_id'] ?? 0);
            $personal = $id ? $registros->firstWhere('id', $id) : null;
            if ($id && (! $personal || ! $this->editable($personal, $periodo) || $data['tipocontrato'] === 'REEMPLAZO')) {
                $this->fail('personal_id', 'Seleccione un contrato regular vigente del RUT en el período actual. Un reemplazo se ingresa como nuevo registro.');
            }
            if (! $id && $data['tipocontrato'] !== 'REEMPLAZO' && $registros->contains(fn ($p) => $this->regular($p->tipocontrato))) {
                $this->fail('personal_id', 'Este RUT ya tiene contratos regulares. Seleccione explícitamente el ID del período abierto; si solo tiene contratos de períodos anteriores, utilice la conciliación de la carga completa para conservar su historial. No se duplicará.');
            }

            $establecimiento = Establecimiento::findOrFail($data['establecimiento_id']);
            $valores = array_intersect_key($data, array_flip(self::CAMPOS));
            $valores += ['establecimiento_id' => $establecimiento->id, 'rbd' => $establecimiento->rbd,
                'rut' => $personal?->rut ?? $registros->first()?->rut ?? $rut,
                'anio' => intdiv($periodo, 100), 'mes' => $periodo % 100, 'vigente' => true];
            foreach (['fecha_nacimiento', 'fecha_termino', 'fecha_antiguedad', 'bienios', 'tramo'] as $campo) {
                $valores[$campo] = $valores[$campo] ?? null;
            }
            $propuesta = new ReemplazoPersonal;
            $propuesta->forceFill($valores);
            foreach ($registros as $otro) {
                if ($otro->id !== $id && $otro->vigente && $this->clave($otro) === $this->clave($propuesta)) {
                    $this->fail('padron', 'Ya existe una línea contractual idéntica para este RUT y período. No se duplicó el registro.');
                }
            }
            $restantes = $registros->filter(fn ($p) => $p->id !== $id && $p->vigente && $p->anio * 100 + $p->mes === $periodo);
            $maximo = $this->maximoDocente($restantes->push($propuesta));
            if ($maximo > 44 && (! ($data['autorizar_exceso'] ?? false) || mb_strlen(trim($data['justificacion_exceso'] ?? '')) < 10)) {
                $this->fail('autorizar_exceso', "Este RUT alcanza {$maximo} horas docentes simultáneas entre establecimientos. Requiere autorización explícita y justificación del exceso de 44 horas.");
            }

            $asignaciones = $personal ? $this->asignaciones($personal) : collect();
            $liberador = app(PadronIndividualAsignacionesService::class);
            $seleccionadas = $liberador->preparar($personal, (int) $establecimiento->id, $asignaciones, $data);
            $conservadas = $asignaciones->whereNotIn('id', $seleccionadas->pluck('id'));
            $impacta = $personal && $conservadas->isNotEmpty()
                && ((int) $personal->establecimiento_id !== (int) $establecimiento->id || (int) $data['jornada'] < (int) $personal->jornada);
            if ($impacta && ! ($data['confirmar_asignaciones'] ?? false)) {
                $this->fail('confirmar_asignaciones', 'El traslado o reducción afecta a un funcionario con asignaciones activas. Confirme que revisará Dotación; sus asignaciones no se trasladan ni se eliminan automáticamente.');
            }
            $antes = $personal?->getRawOriginal();
            if ($personal) {
                app(PadronHistorialService::class)->congelarReferencias([$personal->id]);
            } else {
                $personal = new ReemplazoPersonal;
                $valores += ['row_hash' => hash('sha256', 'individual|'.Str::uuid()),
                    'created_by' => $usuario->id, 'source_filename' => 'Ingreso individual'];
            }
            // No se cambian RUT, ID, hash original, bloqueos ni referencias de contratos existentes.
            $personal->forceFill($valores)->save();
            $liberaciones = $liberador->liberar($seleccionadas, (int) $usuario->id);
            DB::table('padron_individual_cambios')->insert([
                'personal_id' => $personal->id, 'usuario_id' => $usuario->id,
                'accion' => $antes ? ($antes['vigente'] ? 'actualizacion' : 'reactivacion') : 'incorporacion', 'justificacion' => $data['justificacion'],
                'antes' => $antes ? json_encode($antes, JSON_THROW_ON_ERROR) : null,
                'despues' => json_encode($personal->getRawOriginal(), JSON_THROW_ON_ERROR),
                'controles' => json_encode(['maximo_horas_docentes' => $maximo,
                    'autorizar_exceso' => $data['autorizar_exceso'] ?? false,
                    'justificacion_exceso' => $data['justificacion_exceso'] ?? null,
                    'confirmar_asignaciones' => $data['confirmar_asignaciones'] ?? false,
                    'asignaciones_ids' => $asignaciones->pluck('id')->all(),
                    'justificacion_liberacion' => $data['justificacion_liberacion'] ?? null,
                    'liberaciones' => $liberaciones], JSON_THROW_ON_ERROR),
                'created_at' => now(),
            ]);
            return $personal;
        });
    }

    public function asignaciones(ReemplazoPersonal $p): Collection
    {
        return app(PadronIndividualAsignacionesService::class)->consultar($p);
    }

    public function maximoDocente(Collection $registros): int
    {
        $eventos = [];
        foreach ($registros as $p) {
            $estatuto = Str::upper(Str::ascii((string) $p->estatuto));
            if (! str_contains($estatuto, 'DOC') && ! str_contains($estatuto, 'PROF')) { continue; }
            $inicio = $p->fecha_ingreso?->format('Y-m-d') ?? '0001-01-01';
            $fin = $p->fecha_termino?->copy()->addDay()->format('Y-m-d');
            $eventos[$inicio] = ($eventos[$inicio] ?? 0) + (int) $p->jornada;
            if ($fin) { $eventos[$fin] = ($eventos[$fin] ?? 0) - (int) $p->jornada; }
        }
        ksort($eventos);
        $actual = $maximo = 0;
        foreach ($eventos as $delta) { $actual += $delta; $maximo = max($maximo, $actual); }
        return $maximo;
    }

    private function clave(ReemplazoPersonal $p): array
    {
        return [(int) $p->anio, (int) $p->mes, (int) $p->establecimiento_id,
            Str::upper(trim((string) $p->tipocontrato)), Str::upper(trim((string) $p->financiamiento)),
            (int) $p->jornada, $p->fecha_ingreso?->format('Y-m-d'), $p->fecha_termino?->format('Y-m-d')];
    }

    private function fail(string $campo, string $mensaje): never
    {
        throw ValidationException::withMessages([$campo => $mensaje]);
    }
}
