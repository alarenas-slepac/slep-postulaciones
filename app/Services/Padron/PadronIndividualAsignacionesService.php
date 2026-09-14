<?php

namespace App\Services\Padron;

use App\Models\ReemplazoPersonal;
use App\Support\Rut;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/** Liberación explícita, dentro de la misma transacción del cambio contractual. */
class PadronIndividualAsignacionesService
{
    public function consultar(ReemplazoPersonal $personal): Collection
    {
        if (! Schema::hasTable('dotacion_docente_asignaciones')) { return collect(); }
        $rut = Rut::normalize($personal->rut);
        $idsRut = ReemplazoPersonal::query()->select('id')
            ->whereRaw("REPLACE(REPLACE(REPLACE(UPPER(TRIM(rut)), '.', ''), '-', ''), ' ', '') = ?", [$rut]);
        return DB::table('dotacion_docente_asignaciones')->where('anio', $personal->anio)->where('estado', 'activa')
            ->where(function ($q) use ($idsRut, $rut) {
                $q->whereIn('reemplazos_personal_id', $idsRut)
                    ->orWhereRaw("REPLACE(REPLACE(REPLACE(UPPER(TRIM(docente_rut)), '.', ''), '-', ''), ' ', '') = ?", [$rut]);
            })->orderBy('id')->when(DB::transactionLevel() > 0, fn ($q) => $q->lockForUpdate())->get();
    }

    public function huella(object $asignacion): string
    {
        return hash('sha256', json_encode($asignacion, JSON_THROW_ON_ERROR));
    }

    public function preparar(?ReemplazoPersonal $personal, int $destino, Collection $actuales, array $data): Collection
    {
        $ids = array_map('intval', $data['liberar_asignaciones'] ?? []);
        if ($ids === []) { return collect(); }
        if (! $personal) { $this->fail('La liberación requiere seleccionar un contrato existente del funcionario.'); }
        if (! ($data['confirmar_liberacion'] ?? false) || mb_strlen(trim($data['justificacion_liberacion'] ?? '')) < 10) {
            $this->fail('Confirme expresamente la liberación y escriba una justificación de al menos 10 caracteres.');
        }
        $seleccionadas = $actuales->whereIn('id', $ids)->values();
        if ($seleccionadas->count() !== count($ids)) {
            $this->fail('Una asignación seleccionada ya no está activa, es de otro año o no corresponde al funcionario. Consulte nuevamente.');
        }
        $rutsPorId = ReemplazoPersonal::whereIn('id', $seleccionadas->pluck('reemplazos_personal_id')->filter())->pluck('rut', 'id');
        $rut = Rut::normalize($personal->rut);
        foreach ($seleccionadas as $a) {
            if ((int) $a->establecimiento_id === $destino) {
                $this->fail('No puede liberar asignaciones del establecimiento de destino desde el flujo de traslado.');
            }
            $rutAsignacion = Rut::normalize($a->docente_rut);
            if (($rutAsignacion && $rutAsignacion !== $rut)
                || ($a->reemplazos_personal_id && Rut::normalize($rutsPorId[$a->reemplazos_personal_id] ?? null) !== $rut)) {
                $this->fail('Una asignación tiene un vínculo contractual o RUT inconsistente. Revise su identidad en Dotación antes de liberarla.');
            }
            $huella = $data['huellas_asignaciones'][$a->id] ?? '';
            if (! hash_equals($this->huella($a), $huella)) {
                $this->fail('Cambió una asignación seleccionada desde que abrió el formulario. Consulte nuevamente y revise las horas antes de liberar.');
            }
        }
        return $seleccionadas;
    }

    public function liberar(Collection $seleccionadas, int $usuario): array
    {
        if (DB::transactionLevel() === 0) { throw new \LogicException('La liberación debe compartir la transacción contractual.'); }
        $auditoria = [];
        foreach ($seleccionadas as $a) {
            $updated = DB::table('dotacion_docente_asignaciones')->where('id', $a->id)->where('estado', 'activa')->update([
                'estado' => 'inactiva', 'updated_by' => $usuario, 'updated_at' => now()->toDateTimeString(),
            ]);
            if ($updated !== 1) { $this->fail('Una asignación cambió durante la liberación; no se guardó el traslado.'); }
            $auditoria[] = ['antes' => (array) $a, 'despues' => (array) DB::table('dotacion_docente_asignaciones')->find($a->id)];
        }
        return $auditoria;
    }

    private function fail(string $mensaje): never
    {
        throw ValidationException::withMessages(['liberar_asignaciones' => $mensaje]);
    }
}
