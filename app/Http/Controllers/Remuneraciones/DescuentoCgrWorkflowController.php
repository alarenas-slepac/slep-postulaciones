<?php

namespace App\Http\Controllers\Remuneraciones;

use App\Http\Controllers\Controller;
use App\Models\DescuentoCgr;
use App\Models\DescuentoCgrArchivo;
use App\Models\DescuentoCgrNotificacion;
use App\Services\Remuneraciones\DescuentoCgrCertificadoService;
use App\Services\Remuneraciones\DescuentoCgrWorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DescuentoCgrWorkflowController extends Controller
{
    public function liquidaciones(Request $request, DescuentoCgr $descuentoCgr, DescuentoCgrWorkflowService $flujo): RedirectResponse
    {
        $tipo = $request->routeIs('descuentos-cgr.auditoria.liquidaciones') ? 'liquidacion_validada' : 'liquidacion';
        $this->autorizar($request, $tipo === 'liquidacion' ? ['admin', 'funcionario_slep'] : ['admin', 'auditoria_slep']);
        $data = $request->validate([
            'liquidaciones' => ['required', 'array', 'min:1'],
            'liquidaciones.*' => ['required', 'file', 'mimes:pdf', 'max:20480'],
        ]);
        $archivos = [];
        foreach ($data['liquidaciones'] as $cuota => $archivo) {
            if (! ctype_digit((string) $cuota) || (int) $cuota < 1 || (int) $cuota > $descuentoCgr->numero_cuotas) {
                throw ValidationException::withMessages(['liquidaciones' => 'La cuota seleccionada no pertenece al cronograma.']);
            }
            $archivos[] = ['archivo' => $archivo, 'cuotas' => [(int) $cuota]];
        }
        $flujo->guardarArchivos($descuentoCgr, $tipo, $archivos, $request->user()->id);

        return back()->with('status', 'Liquidaciones guardadas para las cuotas seleccionadas.');
    }

    public function comprobante(Request $request, DescuentoCgr $descuentoCgr, string $tipo, DescuentoCgrWorkflowService $flujo): RedirectResponse
    {
        $this->autorizar($request, ['admin', 'funcionario_daf']);
        abort_unless(in_array($tipo, ['sigfe', 'tgr', 'transferencia_institucion'], true), 404);
        $data = $request->validate([
            'archivo' => ['required', 'file', 'mimes:pdf', 'max:20480'],
            'cuotas' => ['required', 'array', 'min:1'],
            'cuotas.*' => ['required', 'integer', 'min:1', 'max:'.$descuentoCgr->numero_cuotas, 'distinct'],
            'folio' => ['required', 'string', 'max:100'],
            'fecha_reintegro' => ['required', 'date'],
            'monto_reintegro_pesos' => ['required', 'integer', 'min:1'],
        ]);
        $flujo->guardarArchivos($descuentoCgr, $tipo, [['archivo' => $data['archivo'], 'cuotas' => $data['cuotas']]], $request->user()->id, $data);

        return back()->with('status', $tipo === 'transferencia_institucion'
            ? 'Comprobante de transferencia asociado a las cuotas seleccionadas.'
            : 'Comprobante de reintegro asociado a las cuotas seleccionadas.');
    }

    public function enviarFinanzas(Request $request, DescuentoCgr $descuentoCgr, DescuentoCgrWorkflowService $flujo): RedirectResponse
    {
        $this->autorizar($request, ['admin', 'funcionario_slep']);
        $flujo->avanzar($descuentoCgr, 'ingresado', 'descuentos_realizados', ['liquidacion'], 'enviado_finanzas_en', 'funcionario_daf', 'finanzas');

        return back()->with('status', 'Descuento enviado a Finanzas.');
    }

    public function enviarAuditoria(Request $request, DescuentoCgr $descuentoCgr, DescuentoCgrWorkflowService $flujo): RedirectResponse
    {
        $this->autorizar($request, ['admin', 'funcionario_daf']);
        $flujo->avanzar($descuentoCgr, 'descuentos_realizados', 'en_auditoria', ['sigfe', 'tgr'], 'enviado_auditoria_en', 'auditoria_slep', 'auditoria');

        return back()->with('status', 'Descuento enviado a Auditoría Interna.');
    }

    public function archivo(DescuentoCgr $descuentoCgr, DescuentoCgrArchivo $archivo): mixed
    {
        abort_unless($archivo->descuento_cgr_id === $descuentoCgr->id, 404);
        abort_unless(Storage::disk('local')->exists($archivo->path), 404);

        return Storage::disk('local')->response($archivo->path, $archivo->nombre_original, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline']);
    }

    public function certificado(Request $request, DescuentoCgr $descuentoCgr, DescuentoCgrWorkflowService $flujo, DescuentoCgrCertificadoService $certificados): mixed
    {
        $this->autorizar($request, ['admin', 'auditoria_slep']);
        $firmadoAnterior = null;
        $contenido = DB::transaction(function () use ($descuentoCgr, $request, $flujo, $certificados, &$firmadoAnterior): string {
            $registro = DescuentoCgr::query()->lockForUpdate()->findOrFail($descuentoCgr->id);
            $flujo->exigirEstado($registro, 'en_auditoria');
            if (! $flujo->completos($registro, ['liquidacion_validada'])) {
                throw ValidationException::withMessages(['documentos' => 'Carga todas las liquidaciones validadas antes de generar el certificado.']);
            }
            $contenido = $certificados->generar($registro, $request->user());
            $firmadoAnterior = $registro->certificado_firmado_path;
            $registro->update(['certificado_generado_en' => now(), 'certificado_generado_por_id' => $request->user()->id,
                'certificado_firmado_path' => null, 'certificado_firmado_nombre' => null, 'certificado_firmado_en' => null, 'certificado_firmado_por_id' => null]);

            return $contenido;
        });
        if ($firmadoAnterior) {
            Storage::disk('local')->delete($firmadoAnterior);
        }

        return response($contenido, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'Content-Disposition' => 'attachment; filename="certificado-reintegro-cgr-'.$descuentoCgr->id.'.docx"',
        ]);
    }

    public function certificadoFirmado(Request $request, DescuentoCgr $descuentoCgr, DescuentoCgrWorkflowService $flujo): RedirectResponse
    {
        $this->autorizar($request, ['admin', 'auditoria_slep']);
        $flujo->exigirEstado($descuentoCgr, 'en_auditoria');
        if (! $flujo->completos($descuentoCgr, ['liquidacion_validada'])) {
            throw ValidationException::withMessages(['documentos' => 'Carga todas las liquidaciones validadas antes del certificado firmado.']);
        }
        if (! $descuentoCgr->certificado_generado_en) {
            throw ValidationException::withMessages(['certificado_pdf' => 'Primero genera el certificado Word desde la plantilla.']);
        }
        $data = $request->validate(['certificado_pdf' => ['required', 'file', 'mimes:pdf', 'max:20480']]);
        $archivo = $data['certificado_pdf'];
        $path = $archivo->storeAs("descuentos-cgr/{$descuentoCgr->id}/certificados", Str::uuid().'.pdf', 'local');
        if (! $path) {
            throw new \RuntimeException('No fue posible guardar el certificado firmado.');
        }
        $anterior = null;
        try {
            DB::transaction(function () use ($descuentoCgr, $request, $archivo, $path, $flujo, &$anterior): void {
                $registro = DescuentoCgr::query()->lockForUpdate()->findOrFail($descuentoCgr->id);
                $flujo->exigirEstado($registro, 'en_auditoria');
                if (! $flujo->completos($registro, ['liquidacion_validada']) || ! $registro->certificado_generado_en) {
                    throw ValidationException::withMessages(['certificado_pdf' => 'Primero completa las liquidaciones y genera el certificado Word.']);
                }
                $anterior = $registro->certificado_firmado_path;
                $registro->update(['certificado_firmado_path' => $path, 'certificado_firmado_nombre' => $archivo->getClientOriginalName(), 'certificado_firmado_en' => now(), 'certificado_firmado_por_id' => $request->user()->id]);
            });
        } catch (\Throwable $error) {
            Storage::disk('local')->delete($path);
            throw $error;
        }
        if ($anterior) {
            Storage::disk('local')->delete($anterior);
        }

        return back()->with('status', 'Certificado firmado cargado.');
    }

    public function verCertificado(DescuentoCgr $descuentoCgr): mixed
    {
        abort_unless($descuentoCgr->certificado_firmado_path && Storage::disk('local')->exists($descuentoCgr->certificado_firmado_path), 404);

        return Storage::disk('local')->response($descuentoCgr->certificado_firmado_path, $descuentoCgr->certificado_firmado_nombre, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline']);
    }

    public function cerrar(Request $request, DescuentoCgr $descuentoCgr, DescuentoCgrWorkflowService $flujo): RedirectResponse
    {
        $this->autorizar($request, ['admin', 'auditoria_slep']);
        DB::transaction(function () use ($descuentoCgr, $flujo): void {
            $registro = DescuentoCgr::query()->lockForUpdate()->findOrFail($descuentoCgr->id);
            $flujo->exigirEstado($registro, 'en_auditoria');
            if (! $flujo->completos($registro, ['liquidacion_validada']) || ! $registro->certificado_firmado_path || ! Storage::disk('local')->exists($registro->certificado_firmado_path)) {
                throw ValidationException::withMessages(['documentos' => 'Se requieren todas las liquidaciones validadas y el certificado PDF firmado.']);
            }
            $registro->update(['estado' => 'cerrado', 'cerrado_en' => now()]);
        });

        return redirect()->route('descuentos-cgr.index', ['estado' => 'cerrado'])->with('status', 'Alzamiento realizado. Registro cerrado.');
    }

    public function configuracion(): mixed
    {
        abort_unless(request()->user()->hasRole('admin'), 403);

        return view('remuneraciones.descuentos-cgr.notificaciones', ['configuraciones' => DescuentoCgrNotificacion::query()->pluck('correos_adicionales', 'evento')]);
    }

    public function guardarConfiguracion(Request $request): RedirectResponse
    {
        $this->autorizar($request, ['admin']);
        $data = $request->validate([
            'finanzas' => ['nullable', 'string', 'max:5000'],
            'auditoria' => ['nullable', 'string', 'max:5000'],
        ]);
        $normalizados = [];
        foreach (['finanzas', 'auditoria'] as $evento) {
            $correos = preg_split('/[,;\s]+/', trim((string) ($data[$evento] ?? ''))) ?: [];
            foreach ($correos as $correo) {
                if ($correo !== '' && ! filter_var($correo, FILTER_VALIDATE_EMAIL)) {
                    throw ValidationException::withMessages([$evento => 'Revisa los correos electrónicos ingresados.']);
                }
            }
            $normalizados[$evento] = implode("\n", array_unique(array_filter($correos)));
        }
        DB::transaction(function () use ($normalizados): void {
            foreach ($normalizados as $evento => $correos) {
                DescuentoCgrNotificacion::query()->updateOrCreate(['evento' => $evento], ['correos_adicionales' => $correos]);
            }
        });

        return back()->with('status', 'Destinatarios adicionales guardados.');
    }

    private function autorizar(Request $request, array $roles): void
    {
        abort_unless($request->user()?->hasAnyRole($roles), 403);
    }
}
