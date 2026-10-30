<?php

namespace App\Services\Maternidad;

use App\Models\MaternidadCaso;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class MaternidadRegistroService
{
    public function puedeAmpliarDominga(): bool
    {
        $usuario = auth()->user();
        return $usuario && $usuario->hasAnyRole(config('maternidad.roles_ampliacion_dominga'))
            && in_array($usuario->activeRoleName(), config('maternidad.roles_ampliacion_dominga'), true);
    }
    public const FECHAS = ['fecha_ingreso', 'fecha_termino', 'fecha_registro', 'fecha_certificado_embarazo',
        'fpc', 'fpp', 'fnh', 'fecha_ley_dominga', 'fecha_inicio_lactancia', 'fecha_inicio_postnatal',
        'fecha_termino_postnatal', 'fecha_rex_postnatal', 'fecha_rex_dominga', 'fecha_rex_lactancia'];
    public const CAMPOS = ['referencia_caso', 'rut', 'nombres', 'apellido_paterno', 'apellido_materno', 'estamento',
        'calidad_juridica', 'ley_dominga', 'lactancia', 'postnatal_parental', 'modalidad_postnatal', 'horas_semanales_lactancia'];

    public function preparar(array $entrada, array $archivos = [], ?MaternidadCaso $caso = null, bool $masivo = false): array
    {
        $estructura = ['rut' => ['sometimes', 'string', 'max:32'], 'fuente' => ['nullable', 'string', 'max:50'], 'rbd' => ['nullable', 'string', 'max:16'],
            'referencia_caso' => ['nullable', 'string', 'max:100'], 'observacion' => ['nullable', 'string', 'max:5000'],
            'regla_fuero_dominga' => ['nullable', 'string'], 'causal_ampliacion_dominga' => ['nullable', 'string'], 'motivo_ampliacion_dominga' => ['nullable', 'string', 'max:2000']];
        foreach (self::FECHAS as $campo) $estructura[$campo] = ['nullable', 'date_format:Y-m-d'];
        Validator::make($entrada, $estructura)->validate();
        $rut = MaternidadRut::normalizar((string) ($entrada['rut'] ?? $caso?->rut));
        if ($caso && $rut !== $caso->rut) throw ValidationException::withMessages(['rut' => 'El RUT del caso no se puede cambiar. Registre otro caso si corresponde.']);
        $fuente = $caso ? $caso->only(['establecimiento_id', 'establecimiento_nombre', 'establecimiento_rbd', 'comuna',
            'fuente_personal', 'fuente_personal_id', 'fuente_snapshot'])
            : app(MaternidadPersonalResolver::class)->resolver($rut, $entrada['fuente'] ?? null, isset($entrada['rbd']) ? (string) $entrada['rbd'] : null);
        $datos = array_merge($fuente, Arr::only($entrada, [...self::CAMPOS, ...self::FECHAS]));
        // Formularios anteriores y casos históricos conservan los nuevos antecedentes al omitirlos.
        foreach (['fecha_rex_dominga', 'fecha_rex_lactancia', 'horas_semanales_lactancia'] as $campo) {
            if (! array_key_exists($campo, $entrada)) {
                $anterior = $caso?->$campo;
                $datos[$campo] = $anterior instanceof \DateTimeInterface ? $anterior->format('Y-m-d') : $anterior;
            }
        }
        $horas = $datos['horas_semanales_lactancia'] ?? null;
        if (is_string($horas)) $horas = str_replace(',', '.', trim($horas));
        $datos['horas_semanales_lactancia'] = filled($horas) ? $horas : null;
        $datos['rut'] = $rut;
        $datos['referencia_caso'] = $caso?->referencia_caso ?? trim((string) ($entrada['referencia_caso'] ?? ''));
        if ($datos['referencia_caso'] === '') $datos['referencia_caso'] = (string) Str::uuid();
        foreach (self::FECHAS as $campo) $datos[$campo] = filled($datos[$campo] ?? null) ? (string) $datos[$campo] : null;
        foreach (['ley_dominga', 'lactancia', 'postnatal_parental'] as $campo) {
            $valor = $entrada[$campo] ?? false;
            if (! in_array($valor, [true, false, 0, 1, '0', '1'], true)) throw ValidationException::withMessages([$campo => 'Seleccione Sí o No.']);
            $datos[$campo] = (bool) $valor;
        }
        if (isset($archivos['postnatal_parental'])) $datos['postnatal_parental'] = true;
        $reglas = ['rut' => ['required'], 'referencia_caso' => ['required', 'string', 'max:100',
                Rule::unique('maternidad_casos')->where('rut', $rut)->ignore($caso?->id)],
            'nombres' => ['required', 'string', 'max:150'], 'apellido_paterno' => ['required', 'string', 'max:100'],
            'apellido_materno' => ['nullable', 'string', 'max:100'], 'estamento' => ['required', Rule::in(array_keys(config('maternidad.estamentos')))],
            'calidad_juridica' => ['required', 'string', 'max:120'], 'fecha_registro' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'modalidad_postnatal' => ['nullable', Rule::in(array_keys(config('maternidad.modalidades')))],
            'horas_semanales_lactancia' => ['nullable', 'numeric', 'decimal:0,2', 'between:0.01,168'],
            'observacion' => ['nullable', 'string', 'max:5000'], 'hijos' => ['nullable', 'array', 'max:10'],
            'hijos.*.nombre_completo' => ['nullable', 'string', 'max:255'],
            'hijos.*.fecha_nacimiento' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'hijos.*.rut' => ['nullable', 'string', 'max:32']];
        foreach (self::FECHAS as $campo) $reglas[$campo] ??= ['nullable', 'date_format:Y-m-d'];
        foreach (['fnh', 'fecha_certificado_embarazo', 'fecha_ley_dominga', 'fecha_rex_postnatal', 'fecha_rex_dominga', 'fecha_rex_lactancia'] as $campo) $reglas[$campo][] = 'before_or_equal:today';
        foreach (config('maternidad.documentos') as $tipo => $label) {
            $reglas['documentos.'.$tipo] = ['nullable', 'file', 'mimes:'.($tipo === 'orden_trabajo' ? 'pdf' : 'pdf,jpg,jpeg,png,webp'), 'max:'.config('maternidad.max_documento_kb')];
        }
        $validar = $datos + ['observacion' => $entrada['observacion'] ?? null, 'hijos' => $entrada['hijos'] ?? [], 'documentos' => $archivos];
        $validator = Validator::make($validar, $reglas, [
            'required' => 'Este campo es obligatorio.', 'date_format' => 'Ingrese una fecha válida.',
            'before_or_equal' => 'La fecha no puede ser posterior a hoy.', 'unique' => 'Ya existe un caso con esta referencia para la funcionaria.',
            'mimes' => 'Adjunte un PDF o una imagen JPG, PNG o WEBP; la Orden de trabajo admite sólo PDF.',
            'max.file' => 'El archivo supera el tamaño permitido de 10 MB.',
        ]);
        $validator->after(function ($v) use ($datos, $entrada, $caso, $masivo, $archivos) {
            $comparar = function ($a, $b, $campo, $mensaje) use ($v) {
                if ($a && $b && preg_match('/^\d{4}-\d{2}-\d{2}$/', $a) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $b) && $a < $b) $v->errors()->add($campo, $mensaje);
            };
            $comparar($datos['fecha_termino'], $datos['fecha_ingreso'], 'fecha_termino', 'El término no puede ser anterior al ingreso.');
            $comparar($datos['fpp'], $datos['fpc'], 'fpp', 'El parto probable no puede ser anterior a la concepción informada.');
            $comparar($datos['fnh'], $datos['fpc'], 'fnh', 'El nacimiento no puede ser anterior a la concepción informada.');
            $comparar($datos['fecha_termino_postnatal'], $datos['fecha_inicio_postnatal'], 'fecha_termino_postnatal', 'El término del permiso no puede ser anterior al inicio.');
            foreach (['ley_dominga' => ['fecha_ley_dominga'], 'lactancia' => ['fnh', 'fecha_inicio_lactancia'],
                'postnatal_parental' => ['fecha_inicio_postnatal', 'fecha_termino_postnatal', 'modalidad_postnatal']] as $flag => $requeridos) {
                if ($datos[$flag]) foreach ($requeridos as $campo) if (blank($datos[$campo] ?? null)) $v->errors()->add($campo, 'Complete este dato para registrar el beneficio informado.');
            }
            if ($datos['lactancia'] && $datos['fnh'] && $datos['fecha_inicio_lactancia'] && ! $v->errors()->has('fnh')) {
                $limite = \Carbon\CarbonImmutable::parse($datos['fnh'])->addYearsNoOverflow(2)->toDateString();
                if ($datos['fecha_inicio_lactancia'] < $datos['fnh'] || $datos['fecha_inicio_lactancia'] > $limite) $v->errors()->add('fecha_inicio_lactancia', 'El inicio debe estar entre el nacimiento y el segundo cumpleaños.');
            }
            if (! $masivo && $datos['lactancia'] && ! $caso?->lactancia && blank($datos['horas_semanales_lactancia'])) {
                $v->errors()->add('horas_semanales_lactancia', 'Indique la cantidad de horas de lactancia a la semana.');
            }
            if (! $masivo && $caso?->origen_registro !== 'masivo') {
                $tipos = array_unique([...(array) $caso?->documentos()->pluck('tipo')->all(), ...array_keys($archivos)]);
                foreach ($this->pendientes($datos, $tipos) as $tipo) {
                    $v->errors()->add('documentos.'.$tipo, 'Adjunte el respaldo para registrar este dato o beneficio.');
                }
            }
            if (isset($archivos['embarazo']) && ! $datos['fecha_certificado_embarazo']) $v->errors()->add('fecha_certificado_embarazo', 'Indique la fecha del certificado adjunto.');
            $existentes = $caso?->documentos()->pluck('tipo')->all() ?? [];
            foreach (['rex_postnatal' => 'fecha_rex_postnatal', 'rex_dominga' => 'fecha_rex_dominga', 'rex_lactancia' => 'fecha_rex_lactancia'] as $tipo => $campo) {
                if ((isset($archivos[$tipo]) || in_array($tipo, $existentes, true)) && ! $datos[$campo]) $v->errors()->add($campo, 'Indique la fecha de la REX adjunta.');
            }
        });
        $validator->validate();
        $hijos = [];
        foreach ($entrada['hijos'] ?? [] as $i => $hijo) {
            if (! collect($hijo)->filter(fn ($valor) => filled($valor))->count()) continue;
            $hijos[] = ['nombre_completo' => trim((string) ($hijo['nombre_completo'] ?? '')) ?: null,
                'fecha_nacimiento' => $hijo['fecha_nacimiento'] ?? null,
                'rut' => filled($hijo['rut'] ?? null) ? MaternidadRut::normalizar($hijo['rut'], 'hijos.'.$i.'.rut') : null];
        }
        $rutsHijos = collect($hijos)->pluck('rut')->filter();
        if ($rutsHijos->duplicates()->isNotEmpty() || $rutsHijos->contains($rut)) throw ValidationException::withMessages(['hijos' => 'Revise los RUT de los hijos: no pueden repetirse ni coincidir con la funcionaria.']);
        $permitidos = [...self::CAMPOS, ...self::FECHAS, 'establecimiento_id', 'establecimiento_nombre', 'establecimiento_rbd',
            'comuna', 'fuente_personal', 'fuente_personal_id'];
        $datos = Arr::only($datos, $permitidos);
        $datos['fuente_snapshot'] = $caso?->fuente_snapshot ?? Arr::except($fuente, ['fuente_snapshot']);
        $regla = $entrada['regla_fuero_dominga'] ?? $caso?->regla_fuero_dominga ?? config('maternidad.regla_fuero_dominga');
        $causal = $entrada['causal_ampliacion_dominga'] ?? $caso?->causal_ampliacion_dominga;
        $motivo = trim((string) ($entrada['motivo_ampliacion_dominga'] ?? $caso?->motivo_ampliacion_dominga)) ?: null;
        $cambioAmpliacion = $regla !== ($caso?->regla_fuero_dominga ?? config('maternidad.regla_fuero_dominga'))
            || $causal !== $caso?->causal_ampliacion_dominga || $motivo !== $caso?->motivo_ampliacion_dominga;
        if ($cambioAmpliacion && ! $this->puedeAmpliarDominga()) throw ValidationException::withMessages(['regla_fuero_dominga' => 'Sólo GDP o Administración pueden modificar la ampliación Ley Dominga.']);
        Validator::make(['regla_fuero_dominga' => $regla, 'causal_ampliacion_dominga' => $causal, 'motivo_ampliacion_dominga' => $motivo], [
            'regla_fuero_dominga' => ['required', Rule::in(['30_dias', 'anio_84_dias'])],
            'causal_ampliacion_dominga' => [$regla === 'anio_84_dias' ? 'required' : 'nullable', Rule::in(array_keys(config('maternidad.causales_ampliacion_dominga')))],
            'motivo_ampliacion_dominga' => [$regla === 'anio_84_dias' ? 'required' : 'nullable', 'string', 'max:2000'],
        ])->validate();
        if ($cambioAmpliacion && $regla === 'anio_84_dias' && ! $datos['ley_dominga']) throw ValidationException::withMessages(['ley_dominga' => 'Active Ley Dominga para registrar su ampliación.']);
        $datos['regla_fuero_dominga'] = $regla;
        $datos['causal_ampliacion_dominga'] = $regla === 'anio_84_dias' ? $causal : null;
        $datos['motivo_ampliacion_dominga'] = $regla === 'anio_84_dias' ? $motivo : null;
        return ['datos' => $datos, 'hijos' => $hijos, 'observacion' => trim((string) ($entrada['observacion'] ?? '')) ?: null];
    }

    public function guardar(array $preparado, array $archivos, int $actor, ?MaternidadCaso $caso = null, ?int $version = null, bool $masivo = false): MaternidadCaso
    {
        $nuevos = [];
        try {
            return DB::transaction(function () use ($preparado, $archivos, $actor, $caso, $version, $masivo, &$nuevos) {
                $esNuevo = $caso === null;
                if ($caso) {
                    $caso = MaternidadCaso::whereKey($caso->id)->lockForUpdate()->firstOrFail();
                    if ($version !== $caso->version) throw ValidationException::withMessages(['version' => 'Otro usuario modificó el caso. Recargue la página antes de guardar.']);
                } else $caso = new MaternidadCaso;
                $antes = $esNuevo ? [] : $this->snapshot($caso);
                $caso->fill($preparado['datos']);
                $caso->origen_registro = $esNuevo ? ($masivo ? 'masivo' : 'individual') : $caso->origen_registro;
                $caso->created_by ??= $actor;
                $caso->updated_by = $actor;
                $caso->version = $esNuevo ? 1 : $caso->version + 1;
                $caso->save();
                foreach ($archivos as $tipo => $archivo) {
                    if (! $archivo instanceof UploadedFile || ! array_key_exists($tipo, config('maternidad.documentos'))) continue;
                    $path = $archivo->store('maternidad/casos/'.$caso->uuid, 'local');
                    if (! $path) throw ValidationException::withMessages(['documentos.'.$tipo => 'No se pudo guardar el documento. Intente nuevamente.']);
                    $nuevos[] = $path;
                    $caso->documentos()->create(['tipo' => $tipo, 'path' => $path, 'nombre_original' => Str::limit(basename($archivo->getClientOriginalName()), 240, ''),
                        'mime' => $archivo->getMimeType(), 'size' => $archivo->getSize(), 'sha256' => hash_file('sha256', $archivo->getRealPath()), 'created_by' => $actor]);
                }
                $caso->hijos()->delete();
                foreach ($preparado['hijos'] as $orden => $hijo) $caso->hijos()->create($hijo + ['orden' => $orden]);
                $tipos = $caso->documentos()->pluck('tipo')->unique()->all();
                $datos = $preparado['datos'];
                $caso->fill(app(MaternidadFechas::class)->calcular($datos, $tipos));
                $caso->documentos_pendientes = $this->pendientes($datos, $tipos);
                $caso->save();
                $despues = $this->snapshot($caso);
                $cambios = [];
                foreach ($despues as $campo => $valor) if (($antes[$campo] ?? null) !== $valor) $cambios[$campo] = ['antes' => $antes[$campo] ?? null, 'despues' => $valor];
                $caso->historial()->create(['evento' => $esNuevo ? ($masivo ? 'importacion' : 'creacion') : 'actualizacion',
                    'observacion' => $preparado['observacion'], 'cambios' => $cambios, 'created_by' => $actor, 'created_at' => now()]);
                return $caso;
            });
        } catch (\Throwable $error) {
            foreach ($nuevos as $path) Storage::disk('local')->delete($path);
            throw $error;
        }
    }

    public function pendientes(array $datos, array $tipos): array
    {
        $requeridos = ['embarazo'];
        if (! empty($datos['fnh'])) $requeridos[] = 'nacimiento';
        foreach (['ley_dominga', 'lactancia', 'postnatal_parental'] as $tipo) if ($datos[$tipo] ?? false) $requeridos[] = $tipo;
        if (! empty($datos['fecha_rex_postnatal'])) $requeridos[] = 'rex_postnatal';
        if (! empty($datos['fecha_rex_dominga'])) $requeridos[] = 'rex_dominga';
        if (! empty($datos['fecha_rex_lactancia'])) $requeridos[] = 'rex_lactancia';
        return array_values(array_diff($requeridos, $tipos));
    }

    private function snapshot(MaternidadCaso $caso): array
    {
        return Arr::except($caso->fresh()->toArray(), ['created_at', 'updated_at', 'version', 'created_by', 'updated_by'])
            + ['hijos' => $caso->hijos()->get(['nombre_completo', 'fecha_nacimiento', 'rut'])->toArray(),
                'documentos' => $caso->documentos()->get(['id', 'tipo', 'nombre_original', 'created_at'])->toArray()];
    }
}
