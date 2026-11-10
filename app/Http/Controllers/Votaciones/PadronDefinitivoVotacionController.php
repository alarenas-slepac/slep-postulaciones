<?php

namespace App\Http\Controllers\Votaciones;

use App\Http\Controllers\Controller;
use App\Models\JornadaVotacion;
use App\Models\PadronDefinitivoVotacion;
use App\Services\Votaciones\PadronConsultaExcel;
use App\Services\Votaciones\PadronDefinitivoService;
use App\Services\Votaciones\ParticipacionVotacionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class PadronDefinitivoVotacionController extends Controller
{
    public function preparar(Request $request, JornadaVotacion $jornada, PadronConsultaExcel $excel, PadronDefinitivoService $service)
    {
        $request->validate(['archivo_definitivo' => ['required', 'file', 'mimes:xlsx', 'max:10240'],
            'certificado_dotacion' => ['nullable', 'file', 'mimes:pdf', 'max:10240']]);
        try {
            $analisis = $excel->analizar($request->file('archivo_definitivo')->getRealPath());
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages(['archivo_definitivo' => $e->getMessage()]);
        }
        $version = $this->guardarSeguro(fn () => $service->preparar($jornada, $analisis, $request->user(), $request->file('certificado_dotacion')), $jornada);
        return redirect()->route('votaciones.admin.padrones.definitivo.revision', [$jornada, $version])
            ->with('success', 'Padrón definitivo preparado para revisión. La vista pública conserva su información vigente.');
    }

    public function revision(Request $request, JornadaVotacion $jornada, PadronDefinitivoVotacion $version, PadronDefinitivoService $service)
    {
        $this->autorizarVersion($jornada, $version);
        $data = $request->validate(['fecha' => ['nullable', 'date_format:Y-m-d']]);
        $fecha = $data['fecha'] ?? null;
        ['dias' => $dias, 'resumen' => $resumen] = $service->resultadosRevision($jornada, $fecha);
        $calculador = app(ParticipacionVotacionService::class);
        $actual = $calculador->resumir($jornada, $resumen);
        $participacion = $calculador->calcular($resumen, $version->personas);
        $padronDefinitivo = $service->contexto($version, true);
        $publicable = ! $version->publicado_at && $service->pendiente($jornada)?->id === $version->id;
        return view('votaciones.admin.padron-definitivo', compact('jornada', 'version', 'dias', 'resumen', 'fecha', 'actual', 'participacion', 'padronDefinitivo', 'publicable'));
    }

    public function publicar(Request $request, JornadaVotacion $jornada, PadronDefinitivoVotacion $version, PadronDefinitivoService $service)
    {
        $this->autorizarVersion($jornada, $version);
        $request->validate(['confirmar_definitivo' => ['required', 'accepted']]);
        $this->guardarSeguro(fn () => $service->publicar($jornada, $version, $request->user()), $jornada);
        return redirect()->route('votaciones.admin.padrones.index', ['jornada' => $jornada->slug])
            ->with('success', 'Padrón definitivo publicado. Se actualizaron los porcentajes de participación y se habilitó la consulta por RUT.');
    }

    public function certificado(Request $request, JornadaVotacion $jornada, PadronDefinitivoVotacion $version)
    {
        $this->autorizarVersion($jornada, $version);
        return $this->archivo($request, $version);
    }

    public function certificadoPublico(Request $request, JornadaVotacion $jornada, PadronDefinitivoVotacion $version, PadronDefinitivoService $service)
    {
        $this->autorizarVersion($jornada, $version);
        abort_unless($version->publicado_at && $service->publicado($jornada)?->id === $version->id
            && $jornada->estado !== JornadaVotacion::SUSPENDIDA && $jornada->procesos()->where('activo', true)->exists(), 404);
        return $this->archivo($request, $version);
    }

    private function archivo(Request $request, PadronDefinitivoVotacion $version)
    {
        abort_unless($version->certificado_path && Storage::disk('local')->exists($version->certificado_path), 404);
        $path = Storage::disk('local')->path($version->certificado_path);
        $nombre = 'certificado-dotacion-padron-'.$version->id.'.pdf';
        return $request->boolean('descargar') ? response()->download($path, $nombre, ['X-Content-Type-Options' => 'nosniff'])
            : response()->file($path, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="'.$nombre.'"', 'X-Content-Type-Options' => 'nosniff']);
    }

    private function autorizarVersion(JornadaVotacion $jornada, PadronDefinitivoVotacion $version): void
    {
        abort_unless((int) $version->jornada_votacion_id === (int) $jornada->id, 404);
    }

    private function guardarSeguro(callable $operacion, JornadaVotacion $jornada): mixed
    {
        try { return $operacion(); }
        catch (ValidationException $e) { throw $e; }
        catch (\Throwable $e) {
            Log::error('No se completó la operación del padrón definitivo.', ['jornada_id' => $jornada->id, 'tipo' => get_class($e)]);
            throw ValidationException::withMessages(['definitivo' => 'No se aplicaron cambios. No fue posible guardar el padrón definitivo; vuelve a intentarlo.']);
        }
    }
}
