<?php

namespace App\Services\Votaciones;

use App\Models\JornadaVotacion;
use App\Models\PadronDefinitivoVotacion;
use App\Models\User;
use App\Support\Votaciones\PapeletasVotacion;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class PadronDefinitivoService
{
    public function disponible(): bool
    {
        return Schema::hasTable('votacion_padrones_definitivos');
    }

    public function publicado(?JornadaVotacion $jornada): ?PadronDefinitivoVotacion
    {
        return $jornada && $this->disponible()
            ? PadronDefinitivoVotacion::select(['id', 'jornada_votacion_id', 'personas', 'certificado_path', 'publicado_at'])
                ->where('jornada_votacion_id', $jornada->id)->whereNotNull('publicado_at')->latest('id')->first()
            : null;
    }

    public function pendiente(JornadaVotacion $jornada): ?PadronDefinitivoVotacion
    {
        return $this->disponible()
            ? PadronDefinitivoVotacion::select(['id', 'jornada_votacion_id', 'personas', 'publicado_at'])
                ->where('jornada_votacion_id', $jornada->id)->whereNull('publicado_at')
                ->where('id', '>', $this->publicado($jornada)?->id ?? 0)->latest('id')->first()
            : null;
    }

    public function preparar(JornadaVotacion $jornada, array $datos, User $usuario, ?UploadedFile $certificado): PadronDefinitivoVotacion
    {
        if (! $this->disponible()) {
            throw ValidationException::withMessages(['archivo_definitivo' => 'La preparación del padrón definitivo requiere actualizar la base de datos del módulo.']);
        }
        if (($datos['resumen']['errores'] ?? 1) > 0 || empty($datos['padron']) || empty($datos['locales'])) {
            throw ValidationException::withMessages(['archivo_definitivo' => 'Corrige las observaciones del Excel antes de preparar el padrón definitivo.']);
        }
        $ruta = null;
        try {
            if ($certificado) {
                if (file_get_contents($certificado->getRealPath(), false, null, 0, 5) !== '%PDF-') {
                    throw ValidationException::withMessages(['certificado_dotacion' => 'El certificado debe ser un documento PDF válido.']);
                }
                $ruta = $certificado->store('votaciones/certificados-dotacion', 'local');
                if (! $ruta) { throw new \RuntimeException('No fue posible guardar el certificado.'); }
            }
            return DB::transaction(function () use ($jornada, $datos, $usuario, $ruta) {
                $locked = JornadaVotacion::query()->lockForUpdate()->findOrFail($jornada->id);
                $version = PadronDefinitivoVotacion::create([
                    'jornada_votacion_id' => $locked->id,
                    'personas' => count(array_unique(array_column($datos['padron'], 'rut'))),
                    'datos' => ['padron' => $datos['padron'], 'locales' => $datos['locales']],
                    'resumen' => $datos['resumen'], 'base_huella' => $this->huella($locked),
                    'certificado_path' => $ruta, 'creado_por' => $usuario->id,
                ]);
                app(BitacoraVotacionService::class)->registrar($locked, 'padron_definitivo_preparado',
                    'Se preparó un padrón definitivo para revisión privada.', $usuario,
                    metadata: ['version_id' => $version->id, 'personas' => $version->personas, 'con_certificado' => (bool) $ruta]);
                return $version;
            });
        } catch (\Throwable $e) {
            if ($ruta) { Storage::disk('local')->delete($ruta); }
            throw $e;
        }
    }

    public function publicar(JornadaVotacion $jornada, PadronDefinitivoVotacion $version, User $usuario): void
    {
        DB::transaction(function () use ($jornada, $version, $usuario): void {
            $locked = JornadaVotacion::query()->lockForUpdate()->findOrFail($jornada->id);
            $version = PadronDefinitivoVotacion::where('jornada_votacion_id', $locked->id)->lockForUpdate()->findOrFail($version->id);
            if ($version->publicado_at || $this->pendiente($locked)?->id !== $version->id
                || ! hash_equals($version->base_huella, $this->huella($locked))) {
                throw ValidationException::withMessages(['definitivo' => 'La revisión cambió o ya fue publicada. Recarga la pantalla y prepara una nueva versión si cambió el padrón vigente.']);
            }
            if ($version->certificado_path && ! Storage::disk('local')->exists($version->certificado_path)) {
                throw ValidationException::withMessages(['definitivo' => 'No está disponible el certificado adjunto. Prepara nuevamente la carga antes de publicar.']);
            }
            $datos = $version->datos + ['resumen' => $version->resumen];
            $servicio = app(PadronConsultaService::class);
            $servicio->importar($locked, $datos, $usuario, true, true);
            $servicio->publicar($locked, true, $usuario);
            $version->update(['publicado_at' => now(), 'publicado_por' => $usuario->id]);
            app(BitacoraVotacionService::class)->registrar($locked, 'padron_definitivo_publicado',
                'Se publicó el padrón definitivo y se activaron los indicadores respecto de sus personas habilitadas.', $usuario,
                metadata: ['version_id' => $version->id, 'personas' => $version->personas, 'con_certificado' => (bool) $version->certificado_path]);
        });
    }

    public function contexto(?PadronDefinitivoVotacion $version, bool $revision = false): ?array
    {
        if (! $version) { return null; }
        $parametros = ['jornada' => $version->jornada_votacion_id, 'version' => $version->id];
        // Las rutas de jornada usan slug, no ID.
        $parametros['jornada'] = $version->jornada->slug;
        $ruta = $revision ? 'votaciones.admin.padrones.definitivo.certificado' : 'public.consulta-votacion.certificado';
        $certificado = $version->certificado_path && Storage::disk('local')->exists($version->certificado_path);
        return ['personas' => $version->personas, 'fecha' => $version->publicado_at?->timezone(config('votaciones.timezone'))->format('d-m-Y H:i'),
            'revision' => $revision && ! $version->publicado_at, 'ver_certificado' => $certificado ? route($ruta, $parametros) : null,
            'descargar_certificado' => $certificado ? route($ruta, $parametros + ['descargar' => 1]) : null];
    }

    /** Reutiliza exclusivamente los escrutinios ya publicados, sin mezclar correcciones pendientes. */
    public function resultadosRevision(JornadaVotacion $jornada, ?string $fecha): array
    {
        $publicado = $jornada->resultadosConsolidados()->publicados()->latest('version')->first();
        $dias = $publicado ? collect($publicado->dias)->map(fn ($dia) => (object) ['fecha' => \Carbon\Carbon::parse($dia['fecha']), 'conteos' => $dia['conteos']])
            : ($jornada->resultadosConsolidados()->whereNotNull('publicado_at')->exists() ? collect()
                : $jornada->resultadosDiarios()->whereNotNull('publicado_at')->orderBy('fecha')->get(['fecha', 'conteos']));
        abort_if($fecha && ! $dias->contains(fn ($dia) => $dia->fecha->toDateString() === $fecha), 404);
        $seleccionados = $fecha ? $dias->filter(fn ($dia) => $dia->fecha->toDateString() === $fecha) : $dias;
        return ['dias' => $dias, 'resumen' => $publicado
            ? ($fecha ? PapeletasVotacion::resumir($publicado->resumen, $seleccionados) : $publicado->resumen)
            : PapeletasVotacion::resumir(PapeletasVotacion::deDias($seleccionados), $seleccionados)];
    }

    private function huella(JornadaVotacion $jornada): string
    {
        return hash('sha256', json_encode([
            $jornada->padronConsulta()->orderBy('id')->get()->map->getAttributes()->all(),
            $jornada->localesConsulta()->orderBy('id')->get()->map->getAttributes()->all(),
        ], JSON_THROW_ON_ERROR));
    }
}
