<?php

namespace App\Services\Remuneraciones;

use App\Models\DescuentoCgr;
use App\Models\DescuentoCgrObservacion;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class DescuentoCgrObservacionService
{
    public const ETAPAS = [
        'ingresado' => 'Ingresados',
        'descuentos_realizados' => 'En registro de Finanzas',
    ];

    public const CAMPOS = [
        'rut' => 'Funcionario / RUT',
        'numero_resolucion' => 'Número de resolución',
        'fecha_resolucion' => 'Fecha de resolución',
        'resolucion_pdf_path' => 'Resolución (PDF)',
        'institucion_reintegro' => 'Institución de reintegro',
        'estamento_funcionario' => 'Estamento o escalafón',
        'deuda_definitiva_pesos' => 'Deuda definitiva en pesos',
        'deuda_equivalente_utm' => 'Deuda equivalente en UTM',
        'cuota_utm' => 'Cuota en UTM',
        'numero_cuotas' => 'Número de cuotas',
        'tasa_interes_anual' => 'Tasa de interés anual',
        'tasa_interes_mensual' => 'Tasa de interés mensual',
        'fecha_primer_descuento' => 'Mes del primer descuento',
        'observaciones' => 'Observaciones del registro',
    ];

    public const DOCUMENTOS = [
        'ingresado' => ['liquidacion' => 'Liquidación de sueldo'],
        'descuentos_realizados' => [
            'sigfe' => 'Comprobante SIGFE',
            'tgr' => 'Comprobante TGR',
            'transferencia_institucion' => 'Transferencia a otra institución',
        ],
    ];

    public const METADATOS = [
        'folio' => 'Folio',
        'fecha_reintegro' => 'Fecha',
        'monto_reintegro_pesos' => 'Monto',
    ];

    public const ROLES_CORRECCION = [
        'ingresado' => 'funcionario_slep',
        'descuentos_realizados' => 'funcionario_daf',
    ];

    public function __construct(private readonly DescuentoCgrWorkflowService $flujo) {}

    public function observar(DescuentoCgr $descuento, string $destino, string $motivo, array $elementos, int $usuarioId): void
    {
        DB::transaction(function () use ($descuento, $destino, $motivo, $elementos, $usuarioId): void {
            $registro = DescuentoCgr::query()->lockForUpdate()->findOrFail($descuento->id);
            $this->flujo->exigirEstado($registro, 'en_auditoria');
            $registro->load('archivos');
            $seleccion = array_fill_keys($elementos, null);
            if (array_diff_key($seleccion, $this->opciones($registro, $destino)) !== []) {
                throw ValidationException::withMessages([
                    'elementos' => 'Selecciona campos y documentos que pueda corregir la etapa de destino.',
                ]);
            }

            $certificado = array_intersect_key($registro->getAttributes(), array_flip([
                'certificado_generado_en', 'certificado_generado_por_id', 'certificado_residuo_utm_aceptado',
                'certificado_firmado_path', 'certificado_firmado_nombre', 'certificado_firmado_en', 'certificado_firmado_por_id',
            ]));
            $validadas = $registro->archivos->where('tipo', 'liquidacion_validada');
            $registro->observacionesAuditoria()->create([
                'etapa_destino' => $destino,
                'observacion' => $motivo,
                'elementos' => array_intersect_key($this->huellas($registro), $seleccion),
                'observada_por_id' => $usuarioId,
                'respaldos_previos' => [
                    'certificado' => array_filter($certificado, fn ($valor) => $valor !== null),
                    'liquidaciones_validadas' => $validadas->map->getAttributes()->values()->all(),
                ],
            ]);
            $registro->archivos()->where('tipo', 'liquidacion_validada')->delete();
            $registro->update(array_fill_keys(array_keys($certificado), null) + ['estado' => $destino]);
        });
    }

    public function reenviar(DescuentoCgr $descuento, string $rol, int $usuarioId): string
    {
        $destino = DB::transaction(function () use ($descuento, $rol, $usuarioId): string {
            $registro = DescuentoCgr::query()->lockForUpdate()->findOrFail($descuento->id);
            $estado = $registro->estadoActual();
            $observacion = $registro->observacionesAuditoria()->whereNull('reenviada_en')->first();
            if (! $observacion || ! isset(self::ROLES_CORRECCION[$estado])) {
                throw ValidationException::withMessages(['estado' => 'El registro ya no tiene una devolución pendiente.']);
            }
            abort_unless(in_array($rol, ['admin', self::ROLES_CORRECCION[$estado]], true), 403);

            $registro->load('archivos');
            if ($this->pendientes($registro, $observacion) !== []) {
                throw ValidationException::withMessages(['elementos' => 'Corrige todos los elementos observados antes de reenviar el registro.']);
            }
            if ($observacion->etapa_destino === 'ingresado' && ! $this->flujo->completos($registro, ['liquidacion'])) {
                throw ValidationException::withMessages(['documentos' => 'Completa las liquidaciones de todas las cuotas antes de reenviar.']);
            }

            $finanzasCompleto = $this->flujo->completos($registro, ['sigfe', 'tgr']);
            if (! $finanzasCompleto && $estado === 'descuentos_realizados') {
                throw ValidationException::withMessages(['documentos' => 'Completa los comprobantes SIGFE y TGR de todas las cuotas.']);
            }

            if (! $finanzasCompleto) {
                $registro->update(['estado' => 'descuentos_realizados', 'enviado_finanzas_en' => now()]);

                return 'descuentos_realizados';
            }

            $fueraDeRango = $registro->archivos->where('numero_cuota', '>', $registro->numero_cuotas);
            if ($fueraDeRango->isNotEmpty()) {
                $observacion->respaldos_previos = $observacion->respaldos_previos + [
                    'cuotas_retiradas' => $fueraDeRango->map->getAttributes()->values()->all(),
                ];
                $registro->archivos()->whereKey($fueraDeRango->modelKeys())->delete();
            }

            $observacion->fill(['reenviada_en' => now(), 'reenviada_por_id' => $usuarioId])->save();
            $registro->update(['estado' => 'en_auditoria', 'enviado_auditoria_en' => now()]);

            return 'en_auditoria';
        });

        if ($destino === 'en_auditoria') {
            $this->flujo->notificarEtapa($descuento, 'auditoria_slep', 'auditoria');
        } else {
            $this->flujo->notificarEtapa($descuento, 'funcionario_daf', 'finanzas');
        }

        return $destino;
    }

    public function opciones(DescuentoCgr $registro, string $destino): array
    {
        $opciones = $destino === 'ingresado' ? self::CAMPOS : [];
        $archivos = $registro->archivos->keyBy(fn ($archivo) => $archivo->tipo.':'.$archivo->numero_cuota);
        foreach (self::DOCUMENTOS[$destino] ?? [] as $tipo => $etiqueta) {
            for ($cuota = 1; $cuota <= $registro->numero_cuotas; $cuota++) {
                $clave = $tipo.':'.$cuota;
                $opciones[$clave] = $this->etiqueta($clave);
                if ($destino === 'descuentos_realizados' && $archivos->has($clave)) {
                    foreach (self::METADATOS as $campo => $nombre) {
                        $opciones[$clave.':'.$campo] = $this->etiqueta($clave.':'.$campo);
                    }
                }
            }
        }

        return $opciones;
    }

    public function etiqueta(string $clave): string
    {
        if (isset(self::CAMPOS[$clave])) {
            return self::CAMPOS[$clave];
        }

        [$tipo, $cuota, $campo] = array_pad(explode(':', $clave), 3, null);
        $tipos = self::DOCUMENTOS['ingresado'] + self::DOCUMENTOS['descuentos_realizados'];

        return ($tipos[$tipo] ?? $tipo).' · Cuota '.$cuota.($campo ? ' · '.self::METADATOS[$campo] : ' · PDF');
    }

    public function pendientes(DescuentoCgr $registro, DescuentoCgrObservacion $observacion): array
    {
        $huellas = $this->huellas($registro);
        $archivos = $registro->archivos->keyBy(fn ($archivo) => $archivo->tipo.':'.$archivo->numero_cuota);
        $pendientes = [];
        foreach ($observacion->elementos as $clave => $anterior) {
            [$tipo, $cuota, $campo] = array_pad(explode(':', $clave), 3, null);
            if ($cuota !== null && (int) $cuota > $registro->numero_cuotas) {
                continue; // La corrección del número de cuotas retiró este respaldo del cronograma.
            }
            $path = $clave === 'resolucion_pdf_path' ? $registro->resolucion_pdf_path
                : ($cuota !== null && $campo === null ? $archivos->get($clave)?->path : null);
            if (($huellas[$clave] ?? null) === $anterior
                || (($clave === 'resolucion_pdf_path' || ($cuota !== null && $campo === null))
                    && (! $path || ! Storage::disk('local')->exists($path)))) {
                $pendientes[] = $clave;
            }
        }

        return $pendientes;
    }

    private function huellas(DescuentoCgr $registro): array
    {
        $valores = [];
        foreach (self::CAMPOS as $campo => $etiqueta) {
            $valores[$campo] = $registro->{$campo};
        }
        for ($cuota = 1; $cuota <= $registro->numero_cuotas; $cuota++) {
            foreach (self::DOCUMENTOS['ingresado'] + self::DOCUMENTOS['descuentos_realizados'] as $tipo => $etiqueta) {
                $valores[$tipo.':'.$cuota] = null;
            }
        }
        foreach ($registro->archivos as $archivo) {
            $clave = $archivo->tipo.':'.$archivo->numero_cuota;
            $valores[$clave] = $archivo->path;
            foreach (self::METADATOS as $campo => $etiqueta) {
                $valores[$clave.':'.$campo] = $archivo->{$campo};
            }
        }

        return array_map(fn ($valor) => hash('sha256', serialize(
            $valor instanceof DateTimeInterface ? $valor->format('Y-m-d') : $valor
        )), $valores);
    }
}
