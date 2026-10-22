<?php

namespace App\Services\Votaciones;

use App\Models\JornadaVotacion;
use App\Models\LocalConsultaVotacion;
use App\Models\User;
use App\Models\VotanteConsulta;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PadronConsultaService
{
    public function importar(JornadaVotacion $jornada, array $analysis, User $user, bool $confirmar): void
    {
        if ($analysis['resumen']['errores'] > 0 || ! $analysis['padron'] || ! $analysis['locales']) {
            throw ValidationException::withMessages(['archivo' => 'Corrige todas las observaciones del archivo antes de importar.']);
        }
        DB::transaction(function () use ($jornada, $analysis, $user, $confirmar): void {
            $locked = JornadaVotacion::query()->lockForUpdate()->findOrFail($jornada->id);
            if ($locked->padronConsulta()->exists() && ! $confirmar) {
                throw ValidationException::withMessages(['confirmar' => 'Confirma el reemplazo del padrón existente de esta jornada.']);
            }
            // Nunca afecta al padrón de personal del SGA ni a otras jornadas.
            $locked->padronConsulta()->delete();
            $locked->localesConsulta()->delete();
            $ids = [];
            foreach ($analysis['locales'] as $key => $local) {
                unset($local['fila_excel']);
                $ids[$key] = LocalConsultaVotacion::create($local + ['jornada_votacion_id' => $locked->id])->id;
            }
            $now = now();
            foreach (array_chunk($analysis['padron'], 250) as $chunk) {
                $rows = [];
                foreach ($chunk as $voter) {
                    $id = $ids[$voter['local_key']];
                    unset($voter['local_key']);
                    $rows[] = $voter + ['jornada_votacion_id' => $locked->id, 'local_consulta_id' => $id, 'created_at' => $now, 'updated_at' => $now];
                }
                VotanteConsulta::insert($rows);
            }
            // Revisar cada nueva carga antes de exponerla públicamente.
            $locked->forceFill(['consulta_publica' => false])->save();
            app(BitacoraVotacionService::class)->registrar($locked, 'padron_consulta_importado', 'Se importó el padrón de consulta y sus locales.', $user, metadata: $analysis['resumen']);
        });
    }

    public function publicar(JornadaVotacion $jornada, bool $publicar, User $user): void
    {
        DB::transaction(function () use ($jornada, $publicar, $user): void {
            $locked = JornadaVotacion::query()->lockForUpdate()->findOrFail($jornada->id);
            if ($publicar && ($locked->estado === JornadaVotacion::SUSPENDIDA || ! $locked->padronConsulta()->exists() || ! $locked->procesos()->where('activo', true)->exists())) {
                throw ValidationException::withMessages(['publicacion' => 'Para publicar, importa un padrón válido, asocia al menos un proceso activo y verifica que la jornada no esté suspendida.']);
            }
            $locked->forceFill(['consulta_publica' => $publicar])->save();
            app(BitacoraVotacionService::class)->registrar($locked, $publicar ? 'consulta_local_publicada' : 'consulta_local_retirada', $publicar ? 'Se habilitó la consulta pública por RUT.' : 'Se retiró la consulta pública por RUT.', $user);
        });
    }
}
