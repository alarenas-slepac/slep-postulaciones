<?php

namespace App\Services\Padron;

use App\Models\CometidoFuncionario;
use App\Models\IncumplimientoLaboral;
use App\Models\ReemplazoPersonalHistorico;
use Illuminate\Support\Facades\Schema;

/** Antecedentes del documento; nunca reconstruye el pasado desde el padrón actual. */
class PadronDocumentoFuncionarioService
{
    public function funcionario(CometidoFuncionario|IncumplimientoLaboral $documento): ReemplazoPersonalHistorico
    {
        $snapshot = $documento->padron_personal_snapshot;
        $personal = $snapshot === null
            ? new ReemplazoPersonalHistorico
            : app(PadronHistorialService::class)->leer($snapshot, (int) $documento->reemplazo_personal_id);

        // Los campos explícitos del documento prevalecen también cuando son nulos.
        $atributos = [
            'id' => $documento->reemplazo_personal_id,
            'establecimiento_id' => $documento->establecimiento_id,
            'rbd' => $documento instanceof CometidoFuncionario ? $documento->rbd : $documento->funcionario_rbd,
            'rut' => $documento->funcionario_rut,
            'nombre' => $documento->funcionario_nombre,
        ];
        if ($documento instanceof CometidoFuncionario) {
            $atributos += [
                'tipocontrato' => $documento->calidad_juridica,
                'estatuto' => $documento->estamento,
                'escalafon' => $documento->cargo_funcion,
            ];
        }
        $personal->setRawAttributes(array_replace($personal->getAttributes(), $atributos), true);
        $personal->exists = true;

        return $personal;
    }

    /** Llamar antes de cambiar la identidad. La copia se persiste junto al documento. */
    public function conservar(CometidoFuncionario|IncumplimientoLaboral $documento): void
    {
        if (! $documento->exists || ! $documento->reemplazo_personal_id) {
            return;
        }
        $personal = $this->funcionario($documento); // Valida cualquier copia existente.
        if ($documento->padron_personal_snapshot === null
            && Schema::hasColumn($documento->getTable(), 'padron_personal_snapshot')) {
            $documento->padron_personal_snapshot = [
                'version' => 1,
                'capturado_at' => now()->toIso8601String(),
                'origen' => 'antecedentes_documento_sin_copia',
                // Copia parcial: no inventar jornada, fechas ni contratos desconocidos.
                'personal' => $personal->getAttributes(),
            ];
        }
    }
}
