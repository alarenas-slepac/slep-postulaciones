<?php

namespace App\Services\Votaciones;

use App\Models\JornadaVotacion;
use App\Models\ResultadoDiarioVotacion;
use App\Models\User;
use App\Support\Votaciones\PapeletasVotacion;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ResultadoDiarioService
{
    public function __construct(private BitacoraVotacionService $bitacora) {}

    public function guardar(JornadaVotacion $jornada, array $data, User $usuario): ResultadoDiarioVotacion
    {
        return DB::transaction(function () use ($jornada, $data, $usuario) {
            // El bloqueo padre serializa también dos primeras altas del mismo día.
            $jornada = JornadaVotacion::query()->lockForUpdate()->findOrFail($jornada->id);
            $resultado = $jornada->resultadosDiarios()->whereDate('fecha', $data['fecha'])->lockForUpdate()->first();
            $papeletas = PapeletasVotacion::para($jornada, $resultado);
            if (! $papeletas) {
                throw ValidationException::withMessages(['jornada' => 'Esta jornada no tiene procesos con papeletas de resultados configuradas.']);
            }
            $this->comprobarVersion($resultado, (int) $data['version']);
            $conteos = [];
            foreach ($papeletas as $clave => $papeleta) {
                foreach ($papeleta['campos'] as $opcion => $label) {
                    $conteos[$clave][$opcion] = (int) $data['conteos'][$clave][$opcion];
                }
            }
            $observacion = trim((string) ($data['observacion_interna'] ?? '')) ?: null;
            if ($resultado && $resultado->conteos === $conteos && $resultado->observacion_interna === $observacion) {
                return $resultado;
            }
            $anterior = $resultado?->conteos;
            $estabaPublicado = $resultado?->publicado_at !== null;
            $resultado ??= new ResultadoDiarioVotacion(['jornada_votacion_id' => $jornada->id, 'fecha' => $data['fecha'], 'creado_por' => $usuario->id]);
            $resultado->fill([
                'conteos' => $conteos, 'observacion_interna' => $observacion,
                'version' => $resultado->exists ? $resultado->version + 1 : 1,
                'publicado_at' => null, 'publicado_por' => null, 'actualizado_por' => $usuario->id,
            ])->save();
            $this->bitacora->registrar($jornada, $anterior === null ? 'resultado_diario_creado' : 'resultado_diario_corregido',
                'Se guardó el escrutinio general del '.$resultado->fecha->format('d-m-Y').'.', $usuario,
                metadata: ['resultado_id' => $resultado->id, 'fecha' => $data['fecha'], 'version' => $resultado->version,
                    'anterior' => $anterior, 'nuevo' => $conteos, 'publicacion_retirada' => $estabaPublicado]);

            return $resultado;
        });
    }

    public function publicar(JornadaVotacion $jornada, ResultadoDiarioVotacion $dia, bool $publicar, int $version, User $usuario): void
    {
        DB::transaction(function () use ($jornada, $dia, $publicar, $version, $usuario): void {
            $jornada = JornadaVotacion::query()->lockForUpdate()->findOrFail($jornada->id);
            $dia = $jornada->resultadosDiarios()->lockForUpdate()->findOrFail($dia->id);
            $this->comprobarVersion($dia, $version);
            if ($publicar && ($jornada->estado === JornadaVotacion::SUSPENDIDA || $dia->fecha->toDateString() > now(config('votaciones.timezone'))->toDateString())) {
                throw ValidationException::withMessages(['publicacion' => 'No se publican resultados futuros ni de una jornada suspendida.']);
            }
            if (($dia->publicado_at !== null) === $publicar) {
                return;
            }
            $dia->update(['publicado_at' => $publicar ? now() : null, 'publicado_por' => $publicar ? $usuario->id : null,
                'actualizado_por' => $usuario->id, 'version' => $dia->version + 1]);
            $this->bitacora->registrar($jornada, $publicar ? 'resultado_diario_publicado' : 'resultado_diario_retirado',
                ($publicar ? 'Se publicó' : 'Se retiró').' el escrutinio general del '.$dia->fecha->format('d-m-Y').'.', $usuario,
                metadata: ['resultado_id' => $dia->id, 'fecha' => $dia->fecha->toDateString(), 'version' => $dia->version]);
        });
    }

    private function comprobarVersion(?ResultadoDiarioVotacion $dia, int $version): void
    {
        if (($dia?->version ?? 0) !== $version) {
            throw ValidationException::withMessages(['version' => 'Este día cambió desde que abriste la pantalla. Vuelve a cargarlo antes de guardar o publicar para no sobrescribir otro escrutinio.']);
        }
    }
}
