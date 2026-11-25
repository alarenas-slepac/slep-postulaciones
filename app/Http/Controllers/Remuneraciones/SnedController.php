<?php

namespace App\Http\Controllers\Remuneraciones;

use App\Http\Controllers\Controller;
use App\Jobs\ProcesarMaeSned;
use App\Models\Establecimiento;
use App\Models\SnedAporte;
use App\Models\SnedCuota;
use App\Services\Remuneraciones\SnedCalculator;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class SnedController extends Controller
{
    public function __construct(private SnedCalculator $calculadora)
    {
        $this->middleware(function (Request $request, $next) {
            $usuario = $request->user();
            $rol = $usuario->activeRoleName();
            abort_unless(in_array($rol, ['admin', 'funcionario_slep'], true) && $usuario->canModule('sned', $rol), 403);

            return $next($request);
        });
    }

    public function index()
    {
        $comunas = Establecimiento::query()->where('rbd', '>', 0)->whereNotNull('comuna')->distinct()->orderBy('comuna')
            ->pluck('comuna')->filter()->keyBy(fn ($comuna) => Str::slug($comuna));
        $cuotas = SnedCuota::query()->orderByDesc('anio')->orderByDesc('numero')->orderBy('comuna')->orderByDesc('version')->paginate(20);

        return response()->view('remuneraciones.sned.index', compact('cuotas', 'comunas'))->header('Cache-Control', 'private, no-store');
    }

    public function store(Request $request)
    {
        $datos = $request->validate([
            'anio' => ['required', 'integer', 'min:2008', 'max:2100'],
            'numero' => ['required', 'integer', 'between:1,4'],
            'comuna' => ['required', 'string', 'max:100'],
        ]);
        $comuna = $this->establecimientos($datos['comuna'])->first()?->comuna;
        if (! $comuna) {
            throw ValidationException::withMessages(['comuna' => 'Seleccione una comuna del catálogo de establecimientos.']);
        }
        $periodo = ['anio' => $datos['anio'], 'numero' => $datos['numero'], 'comuna_clave' => Str::slug($comuna)];
        $cuota = SnedCuota::query()->where($periodo)->latest('version')->first()
            ?? SnedCuota::firstOrCreate($periodo + ['version' => 1], [
                'comuna' => $comuna, 'metodo' => 'jornada_dias', 'creado_por' => $request->user()->id,
            ]);

        return to_route('sned.show', $cuota);
    }

    public function show(Request $request, SnedCuota $cuota)
    {
        $cargas = $this->cargasVigentes($cuota);
        $listas = $this->cargasListas($cargas, $cuota);
        $aportes = $cuota->aportes()->orderBy('establecimiento')->get();
        $aporte = $aportes->firstWhere('rbd', $request->integer('rbd')) ?? $aportes->first();
        $estamento = $request->query('estamento') === 'aaee' ? 'aaee' : 'docentes';
        $establecimientos = $cuota->cerrado_at ? collect() : $this->establecimientos($cuota->comuna_clave)->unique('rbd');
        $resultado = $aporte && $listas
            ? ($cuota->resultados[$aporte->rbd] ?? $this->calculadora->calcular($cuota, $aporte, $cargas))
            : null;
        $ultimaVersion = (int) SnedCuota::query()->where([
            'anio' => $cuota->anio, 'numero' => $cuota->numero, 'comuna_clave' => $cuota->comuna_clave,
        ])->max('version');

        return response()->view('remuneraciones.sned.show', compact(
            'cuota', 'cargas', 'listas', 'aportes', 'aporte', 'establecimientos', 'resultado', 'ultimaVersion', 'estamento'
        ))->header('Cache-Control', 'private, no-store');
    }

    public function cargar(Request $request, SnedCuota $cuota)
    {
        $datos = $request->validate([
            'mes' => ['required', 'integer', Rule::in($cuota->meses())],
            'excel' => ['required', 'file', 'mimes:xlsx', 'max:20480'],
        ]);
        $archivo = $datos['excel'];
        $nombre = Str::limit(basename(str_replace('\\', '/', $archivo->getClientOriginalName())), 240, '');
        if (preg_match('/(?:^|[_ -])(0[1-9]|1[0-2])[_ -](\d{4})\.xlsx$/i', $nombre, $fecha)
            && ((int) $fecha[1] !== (int) $datos['mes'] || (int) $fecha[2] !== (int) $cuota->anio)) {
            throw ValidationException::withMessages(['excel' => 'El mes y año del nombre del MAE no coinciden con la carga seleccionada.']);
        }

        $hash = hash_file('sha256', $archivo->getRealPath());
        $ruta = $archivo->store('sned/mae/'.$cuota->getKey(), 'local');
        if (! $hash || ! $ruta) {
            throw ValidationException::withMessages(['excel' => 'No se pudo guardar el MAE para procesarlo.']);
        }

        try {
            $carga = DB::transaction(function () use ($cuota, $datos, $ruta, $nombre, $hash, $request) {
                $cuota = $this->bloquearBorrador($cuota);

                return $cuota->cargas()->create([
                    'mes' => $datos['mes'], 'estado' => 'pendiente', 'nombre_archivo' => $nombre,
                    'ruta_archivo' => $ruta, 'sha256' => $hash, 'creado_por' => $request->user()->id,
                ]);
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($ruta);
            throw $exception;
        }
        try {
            ProcesarMaeSned::dispatch($carga->id);
        } catch (Throwable) {
            $carga->update(['estado' => 'fallida', 'error' => 'La carga no pudo encolarse. Vuelva a cargar el MAE.']);
            throw ValidationException::withMessages(['excel' => 'El procesamiento en segundo plano no está disponible.']);
        }

        return to_route('sned.show', $cuota)->with('success', 'El MAE quedó en cola para su revisión.');
    }

    public function configurar(Request $request, SnedCuota $cuota)
    {
        $datos = $request->validate([
            'metodo' => ['required', Rule::in(array_keys(SnedCuota::METODOS))],
            'mes_pago' => ['nullable', 'date_format:Y-m', 'after_or_equal:'.sprintf('%04d-%02d', $cuota->anio, max($cuota->meses()))],
            'instruccion_calculo' => ['nullable', 'string', 'max:2000'],
        ]);
        DB::transaction(fn () => $this->bloquearBorrador($cuota)->update($datos));

        return to_route('sned.show', $cuota)->with('success', 'Se guardó el criterio de cálculo.');
    }

    public function guardarAporte(Request $request, SnedCuota $cuota)
    {
        $datos = $request->validate([
            'rbd' => ['required', 'integer', 'min:1'],
            'docentes' => ['required', 'integer', 'min:0', 'max:1000000000000'],
            'aaee' => ['required', 'integer', 'min:0', 'max:1000000000000'],
        ]);
        $establecimiento = $this->establecimientos($cuota->comuna_clave)->firstWhere('rbd', $datos['rbd']);
        if (! $establecimiento || (int) $datos['docentes'] + (int) $datos['aaee'] === 0) {
            throw ValidationException::withMessages(['rbd' => 'Seleccione un establecimiento de la comuna e ingrese un aporte positivo.']);
        }
        DB::transaction(function () use ($request, $cuota, $establecimiento, $datos) {
            $cuota = $this->bloquearBorrador($cuota);
            $cuota->aportes()->updateOrCreate(['rbd' => $datos['rbd']], [
                'establecimiento' => $establecimiento->nombre_establecimiento, 'docentes' => $datos['docentes'],
                'aaee' => $datos['aaee'], 'actualizado_por' => $request->user()->id,
            ]);
        });

        return to_route('sned.show', [$cuota, 'rbd' => $datos['rbd']])->with('success', 'Se guardaron los aportes brutos.');
    }

    public function quitarAporte(SnedCuota $cuota, SnedAporte $aporte)
    {
        DB::transaction(function () use ($cuota, $aporte) {
            $cuota = $this->bloquearBorrador($cuota);
            $cuota->aportes()->findOrFail($aporte->id)->delete();
        });

        return to_route('sned.show', $cuota)->with('success', 'Se quitó el establecimiento del borrador.');
    }

    public function guardarIncentivo(Request $request, SnedCuota $cuota, SnedAporte $aporte)
    {
        $datos = $request->validate([
            'referencia' => ['required', 'string', 'max:2000'],
            'ruts' => ['required', 'array', 'list'],
            'ruts.*' => ['required', 'string', 'max:12', 'distinct'],
        ], [
            'ruts.required' => 'Seleccione al menos un docente para distribuir el incentivo.',
        ]);

        DB::transaction(function () use ($cuota, $aporte, $datos, $request) {
            $cuota = $this->bloquearBorrador($cuota);
            $aporte = $cuota->aportes()->findOrFail($aporte->id);
            $cargas = $this->cargasVigentes($cuota);
            $this->validarCargas($cargas, $cuota);
            $resultado = $this->calculadora->calcular($cuota, $aporte, $cargas);
            $elegibles = array_column(array_filter($resultado['personas'], fn ($persona) => $persona['estamento'] === 'docentes'), 'rut');
            $ruts = $datos['ruts'];
            if (! $aporte->fondoIncentivo() || array_diff($ruts, $elegibles)) {
                throw ValidationException::withMessages(['ruts' => 'Seleccione docentes elegibles de este establecimiento con un fondo de incentivo positivo.']);
            }
            $aporte->update(['incentivo' => [
                'alcance' => count($ruts) === count($elegibles) ? 'todos' : 'seleccion', 'ruts' => $ruts,
                'referencia' => trim($datos['referencia']), 'usuario_id' => $request->user()->id, 'registrado_at' => now()->toDateTimeString(),
            ]]);
        });

        return to_route('sned.show', [$cuota, 'rbd' => $aporte->rbd])->with('success', 'Se guardó la distribución del incentivo docente.');
    }

    public function cerrar(Request $request, SnedCuota $cuota)
    {
        DB::transaction(function () use ($request, $cuota) {
            $cuota = $this->bloquearBorrador($cuota);
            $cargas = $this->cargasVigentes($cuota);
            $this->validarCargas($cargas, $cuota);
            if ($cuota->metodo === 'jornada_dias' && blank($cuota->instruccion_calculo)) {
                throw ValidationException::withMessages(['instruccion_calculo' => 'Registre la referencia de la instrucción del SLEP para usar el prorrateo por días en pago.']);
            }
            $aportes = $cuota->aportes()->get();
            if ($aportes->isEmpty()) {
                throw ValidationException::withMessages(['aportes' => 'Ingrese los aportes de los establecimientos seleccionados.']);
            }
            $resultados = [];
            $errores = [];
            foreach ($aportes as $aporte) {
                $resultado = $this->calculadora->calcular($cuota, $aporte, $cargas);
                if ($resultado['errores']) {
                    $errores[] = "{$aporte->establecimiento} (RBD {$aporte->rbd}). ".implode(' ', $resultado['errores']);
                }
                $resultados[$aporte->rbd] = $resultado;
            }
            if ($errores) {
                throw ValidationException::withMessages(['cierre' => $errores]);
            }
            $cuota->update([
                'resultados' => $resultados, 'cerrado_at' => now(), 'cerrado_por' => $request->user()->id,
            ]);
        });

        return to_route('sned.show', $cuota)->with('success', 'Cuota cerrada. La nómina está disponible para descargar.');
    }

    public function nuevaVersion(Request $request, SnedCuota $cuota)
    {
        $nueva = DB::transaction(function () use ($request, $cuota) {
            $cuota = SnedCuota::query()->lockForUpdate()->findOrFail($cuota->id);
            abort_unless($cuota->cerrado_at, 409);
            $periodo = ['anio' => $cuota->anio, 'numero' => $cuota->numero, 'comuna_clave' => $cuota->comuna_clave];
            $vigente = SnedCuota::query()->where($periodo)->latest('version')->firstOrFail();
            if ($vigente->id !== $cuota->id) {
                return $vigente;
            }
            $nueva = $cuota->replicate(['resultados', 'cerrado_at', 'cerrado_por']);
            $nueva->fill(['version' => $cuota->version + 1, 'creado_por' => $request->user()->id])->save();
            foreach ($this->cargasVigentes($cuota) as $carga) {
                $nueva->cargas()->save($carga->replicate());
            }
            foreach ($cuota->aportes as $aporte) {
                $nueva->aportes()->save($aporte->replicate());
            }

            return $nueva;
        });

        return to_route('sned.show', $nueva);
    }

    public function exportar(SnedCuota $cuota, string $nomina, string $formato)
    {
        $tipo = SnedCuota::NOMINAS[$nomina] ?? null;
        abort_unless($tipo && in_array($formato, $tipo['formatos'], true), 404);
        abort_unless($cuota->cerrado_at, 409, 'Cierre la cuota antes de descargar la nómina.');
        $montos = [];
        foreach ($cuota->resultados as $establecimiento) {
            foreach ($establecimiento['personas'] as $persona) {
                if ($persona['estamento'] === $tipo['estamento']) {
                    $montos[$persona['rut']] = ($montos[$persona['rut']] ?? 0) + $persona[$tipo['campo']];
                }
            }
        }
        ksort($montos, SORT_NATURAL);
        $nombre = sprintf(
            'SNED_CUOTA_%d_%d_%s_%s.%s', $cuota->numero, $cuota->anio,
            strtoupper(Str::slug($cuota->comuna, '_')), $tipo['sufijo'], $formato
        );
        $separador = $formato === 'csv' ? ',' : "\t";

        return response()->streamDownload(function () use ($montos, $separador) {
            $salida = fopen('php://output', 'wb');
            fputcsv($salida, ['RUT', 'MONTO'], $separador, escape: '');
            foreach (array_filter($montos) as $rut => $monto) {
                fputcsv($salida, [$rut, $monto], $separador, escape: '');
            }
            fclose($salida);
        }, $nombre, [
            'Content-Type' => ($formato === 'csv' ? 'text/csv' : 'text/plain').'; charset=UTF-8',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    private function establecimientos(string $comuna): Collection
    {
        $clave = Str::slug($comuna);

        return Establecimiento::query()->where('rbd', '>', 0)->orderBy('nombre_establecimiento')
            ->get(['rbd', 'nombre_establecimiento', 'comuna'])->filter(fn ($ee) => Str::slug($ee->comuna) === $clave);
    }

    private function cargasVigentes(SnedCuota $cuota): Collection
    {
        $vigentes = $cuota->cargas()->selectRaw('MAX(id)')->groupBy('mes')->getQuery();

        return $cuota->cargas()->whereIn('id', $vigentes)->get()->toBase()->keyBy('mes');
    }

    private function cargasListas(Collection $cargas, SnedCuota $cuota): bool
    {
        return $cargas->only($cuota->meses())->count() === 3 && $cargas->every(fn ($carga) => $carga->estado === 'lista');
    }

    private function validarCargas(Collection $cargas, SnedCuota $cuota): void
    {
        if (! $this->cargasListas($cargas, $cuota)) {
            throw ValidationException::withMessages(['cargas' => 'Las tres cargas MAE de la cuota deben estar procesadas correctamente.']);
        }
    }

    private function bloquearBorrador(SnedCuota $cuota): SnedCuota
    {
        $cuota = SnedCuota::query()->lockForUpdate()->findOrFail($cuota->id);
        abort_if($cuota->cerrado_at, 409, 'La cuota está cerrada. Cree una nueva versión para corregirla.');

        return $cuota;
    }
}
