<?php

namespace App\Http\Controllers\Gestion;

use App\Http\Controllers\Controller;
use App\Models\SolicitudReemplazoConfiguracion;
use App\Models\SolicitudReemplazoProrroga;
use App\Services\OrdenTrabajoFirmaService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class OrdenTrabajoFirmaController extends Controller
{
    public function __construct(private readonly OrdenTrabajoFirmaService $firmas) {}

    public function edit(): View
    {
        return view('gestion.solicitudes-reemplazo.firma-ot.edit', [
            'configuracion' => $this->firmas->configuracion(),
            'perfiles' => OrdenTrabajoFirmaService::PERFILES,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $archivosNuevos = [];
        try {
            DB::transaction(function () use ($request, &$archivosNuevos): void {
                $registro = SolicitudReemplazoConfiguracion::query()->lockForUpdate()->createOrFirst(
                    ['clave' => SolicitudReemplazoConfiguracion::FIRMA_ORDEN_TRABAJO],
                    ['nombre' => 'Firma de órdenes de trabajo'],
                );
                $datos = $this->validar($request, $this->firmas->configuracion($registro));
                foreach (array_keys(OrdenTrabajoFirmaService::PERFILES) as $perfil) {
                    if ($imagen = $request->file($perfil.'.firma')) {
                        $ruta = $imagen->store('reemplazos/firmas-ot', 'local');
                        if (! $ruta) {
                            throw ValidationException::withMessages([$perfil.'.firma' => 'No se pudo guardar la imagen de firma.']);
                        }
                        $archivosNuevos[] = $ruta;
                        $datos[$perfil]['archivo'] = $ruta;
                    }
                }
                // Los PDF históricos conservan la referencia al archivo original.
                $registro->update(['valor' => json_encode($datos, JSON_THROW_ON_ERROR), 'updated_by' => $request->user()->id]);
            });
        } catch (Throwable $error) {
            Storage::disk('local')->delete($archivosNuevos);
            throw $error;
        }

        return redirect()->route('gestion.solicitudes-reemplazo.firma-ot.edit')
            ->with('status', 'Configuración de firma guardada.');
    }

    public function preview(Request $request): Response
    {
        $datos = $this->validar($request, $this->firmas->configuracion(), soloVigente: true);
        $vigente = $datos['vigente'];
        $pdf = Pdf::loadView('pdf.orden-trabajo-prorroga', [
            'prorroga' => new SolicitudReemplazoProrroga,
            'numero' => null,
            'reemplazo' => [
                'nombre' => 'Persona de ejemplo', 'establecimiento' => 'Establecimiento de ejemplo',
                'cargo' => 'Docente', 'horas' => 44, 'desglose' => '44 h Subvención General', 'solicitud' => 'EJEMPLO',
            ],
            'vigencia' => ['desde' => '2027-01-01', 'hasta' => '2027-02-28'],
            'motivo' => 'Vista previa de la firma',
            'inicialesEmisor' => '',
            'firmaOt' => $this->firmas->paraPdf($datos[$vigente], $request->file($vigente.'.firma')),
        ])->setPaper('letter', 'portrait')->stream('vista-previa-firma-ot.pdf');
        $pdf->headers->set('Cache-Control', 'private, no-store');

        return $pdf;
    }

    private function validar(Request $request, array $actual, bool $soloVigente = false): array
    {
        $reglas = ['vigente' => ['required', Rule::in(array_keys(OrdenTrabajoFirmaService::PERFILES))]];
        $atributos = ['vigente' => 'perfil de firma vigente'];
        foreach (OrdenTrabajoFirmaService::PERFILES as $perfil => $etiqueta) {
            $reglas[$perfil.'.nombre'] = ['nullable', 'string', 'max:140'];
            $reglas[$perfil.'.cargo'] = ['nullable', 'string', 'max:160'];
            $reglas[$perfil.'.firma'] = ['nullable', 'image', 'mimes:png,jpg,jpeg', 'max:2048', 'dimensions:max_width=1600,max_height=1200'];
            $atributos[$perfil.'.nombre'] = 'nombre del perfil '.$etiqueta;
            $atributos[$perfil.'.cargo'] = 'cargo del perfil '.$etiqueta;
            $atributos[$perfil.'.firma'] = 'imagen de firma del perfil '.$etiqueta;
        }
        $solicitado = $request->validate($reglas, [], $atributos);
        $vigente = $solicitado['vigente'];
        $datos = ['vigente' => $vigente];
        $errores = [];
        foreach (OrdenTrabajoFirmaService::PERFILES as $perfil => $etiqueta) {
            if ($soloVigente && $perfil !== $vigente) {
                continue;
            }
            $nombre = trim($solicitado[$perfil]['nombre'] ?? '');
            $cargo = trim($solicitado[$perfil]['cargo'] ?? '');
            $nuevaFirma = $request->hasFile($perfil.'.firma');
            $archivo = mb_strtolower($nombre) === mb_strtolower($actual[$perfil]['nombre']) ? $actual[$perfil]['archivo'] : null;
            $institucional = $perfil === 'titular' && mb_strtolower($nombre) === mb_strtolower($this->firmas->titularInstitucional()['nombre']);
            if ($perfil === 'titular' || $perfil === $vigente || $nombre !== '' || $cargo !== '' || $nuevaFirma) {
                if ($nombre === '') {
                    $errores[$perfil.'.nombre'] = 'Indica el nombre para el perfil '.$etiqueta.'.';
                }
                if ($cargo === '') {
                    $errores[$perfil.'.cargo'] = 'Indica el cargo para el perfil '.$etiqueta.'.';
                }
                if (! $nuevaFirma && ($archivo ? ! Storage::disk('local')->exists($archivo) : ! $institucional)) {
                    $errores[$perfil.'.firma'] = 'Carga la firma de esta persona.';
                }
            }
            $datos[$perfil] = compact('nombre', 'cargo', 'archivo');
        }
        if ($errores) {
            throw ValidationException::withMessages($errores);
        }

        return $datos;
    }
}
