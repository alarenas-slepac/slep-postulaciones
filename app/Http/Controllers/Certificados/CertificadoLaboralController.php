<?php

namespace App\Http\Controllers\Certificados;

use App\Http\Controllers\Controller;
use App\Models\CertificadoEmitido;
use App\Models\CertificadoImportacion;
use App\Models\User;
use App\Services\Certificados\CertificadoVigenciaLaboralService;
use App\Services\Certificados\CertificadoVigenciaPdfService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CertificadoLaboralController extends Controller
{
    public function __construct(
        private readonly CertificadoVigenciaLaboralService $vigenciaService,
        private readonly CertificadoVigenciaPdfService $pdfService
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $rolActivo = $this->rolActivo($user);
        $puedeEmitirTerceros = $this->esEmisorGeneral($rolActivo);
        abort_unless(
            $puedeEmitirTerceros || $this->esEmisorPropio($rolActivo),
            403
        );
        $rutConsulta = $puedeEmitirTerceros
            ? trim((string) $request->query('rut', ''))
            : (string) $user->rut_normalized;
        $resultado = null;
        $mensajeConsulta = null;

        if ($rutConsulta !== '') {
            try {
                $resultado = $this->vigenciaService->resolver($rutConsulta);
            } catch (DomainException $e) {
                $mensajeConsulta = $e->getMessage();
            }
        }

        $historial = CertificadoEmitido::query()
            ->when(
                ! $puedeEmitirTerceros,
                fn ($query) => $query->where('rut_normalizado', $user->rut_normalized)
            )
            ->latest('emitido_at')
            ->paginate(15)
            ->withQueryString();

        $baseActiva = CertificadoImportacion::query()
            ->where('es_vigente', true)
            ->latest('activado_at')
            ->first();

        return view('certificados.index', compact(
            'resultado',
            'mensajeConsulta',
            'rutConsulta',
            'historial',
            'baseActiva',
            'puedeEmitirTerceros',
            'rolActivo'
        ));
    }

    public function emitir(Request $request): RedirectResponse
    {
        $user = $request->user();
        $rolActivo = $this->rolActivo($user);
        $puedeEmitirTerceros = $this->esEmisorGeneral($rolActivo);

        if (! $puedeEmitirTerceros && ! $this->esEmisorPropio($rolActivo)) {
            abort(403);
        }

        $datos = $request->validate([
            'rut' => [$puedeEmitirTerceros ? 'required' : 'nullable', 'string', 'max:20'],
        ]);
        $rut = $puedeEmitirTerceros
            ? (string) ($datos['rut'] ?? '')
            : (string) $user->rut_normalized;

        try {
            $resultado = $this->vigenciaService->resolver($rut);
        } catch (DomainException $e) {
            throw ValidationException::withMessages(['rut' => $e->getMessage()]);
        }

        $beneficiario = User::query()
            ->where('rut', $resultado['rut_normalizado'])
            ->first();

        try {
            $certificado = DB::transaction(function () use (
                $resultado,
                $beneficiario,
                $user,
                $rolActivo
            ) {
                $certificado = CertificadoEmitido::query()->create([
                    'tipo' => 'vigencia_laboral',
                    'codigo_validacion' => Str::upper(bin2hex(random_bytes(16))),
                    'rut_normalizado' => $resultado['rut_normalizado'],
                    'nombre_snapshot' => $resultado['nombre'],
                    'fecha_antiguedad' => $resultado['fecha_antiguedad']->format('Y-m-d'),
                    'calidad_juridica_snapshot' => $resultado['calidad_juridica'],
                    'regimen_juridico_snapshot' => $resultado['regimen_juridico'],
                    'establecimientos_snapshot' => $resultado['establecimientos'],
                    'contratos_snapshot' => $resultado['contratos'],
                    'importacion_id' => $resultado['importacion']->id,
                    'usuario_beneficiario_id' => $beneficiario?->id,
                    'emitido_por_user_id' => $user->id,
                    'rol_emisor' => $rolActivo,
                    'estado' => 'vigente',
                    'emitido_at' => now(),
                ]);
                $certificado->update([
                    'numero' => 'CV-'.now()->format('Y').'-'
                        .str_pad((string) $certificado->id, 6, '0', STR_PAD_LEFT),
                ]);

                return $certificado->fresh();
            });

            $certificado = $this->pdfService->generar($certificado);
        } catch (\Throwable $e) {
            if (isset($certificado) && $certificado instanceof CertificadoEmitido) {
                $certificado->update(['estado' => 'error_generacion']);
            }
            Log::error('No fue posible generar un certificado de vigencia', [
                'certificado_id' => $certificado->id ?? null,
                'message' => $e->getMessage(),
            ]);

            return back()
                ->withInput()
                ->withErrors([
                    'rut' => 'No fue posible generar el PDF. Intenta nuevamente.',
                ]);
        }

        $parametrosListado = $puedeEmitirTerceros
            ? ['rut' => $resultado['rut_normalizado']]
            : [];

        return redirect()
            ->to(
                route('certificados.index', $parametrosListado)
                    .'#certificados-emitidos'
            )
            ->with(
                'status',
                "Certificado {$certificado->numero} emitido correctamente. "
                    .'Ya se encuentra disponible para ver o descargar.'
            );
    }

    public function ver(Request $request, CertificadoEmitido $certificado): BinaryFileResponse
    {
        $this->autorizarLectura($request, $certificado);

        return $this->respuestaArchivo($certificado, false);
    }

    public function descargar(
        Request $request,
        CertificadoEmitido $certificado
    ): BinaryFileResponse {
        $this->autorizarLectura($request, $certificado);

        return $this->respuestaArchivo($certificado, true);
    }

    public function anular(
        Request $request,
        CertificadoEmitido $certificado
    ): RedirectResponse {
        $rolActivo = $this->rolActivo($request->user());
        abort_unless($this->esEmisorGeneral($rolActivo), 403);

        $datos = $request->validate([
            'motivo' => ['required', 'string', 'min:10', 'max:1000'],
        ]);
        $certificado->update([
            'estado' => 'anulado',
            'anulado_at' => now(),
            'anulado_por_user_id' => $request->user()->id,
            'motivo_anulacion' => $datos['motivo'],
        ]);

        return back()->with('status', 'El certificado fue anulado.');
    }

    private function respuestaArchivo(
        CertificadoEmitido $certificado,
        bool $descargar
    ): BinaryFileResponse {
        abort_unless(
            $certificado->archivo_pdf_path
                && Storage::disk('local')->exists($certificado->archivo_pdf_path),
            404
        );

        $ruta = Storage::disk('local')->path($certificado->archivo_pdf_path);
        $nombre = $certificado->numero.'.pdf';

        return $descargar
            ? response()->download($ruta, $nombre)
            : response()->file($ruta, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="'.$nombre.'"',
            ]);
    }

    private function autorizarLectura(
        Request $request,
        CertificadoEmitido $certificado
    ): void {
        $rolActivo = $this->rolActivo($request->user());
        if ($this->esEmisorGeneral($rolActivo)) {
            return;
        }

        abort_unless(
            $this->esEmisorPropio($rolActivo)
                && hash_equals(
                    (string) $request->user()->rut_normalized,
                    (string) $certificado->rut_normalizado
                ),
            403
        );
    }

    private function rolActivo(User $user): string
    {
        return Str::lower(trim((string) $user->activeRoleName()));
    }

    private function esEmisorGeneral(string $rol): bool
    {
        return in_array(
            $rol,
            (array) config('certificados.roles_emision_general', []),
            true
        );
    }

    private function esEmisorPropio(string $rol): bool
    {
        return in_array(
            $rol,
            (array) config('certificados.roles_emision_propia', []),
            true
        );
    }
}
