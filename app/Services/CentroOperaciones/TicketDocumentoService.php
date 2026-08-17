<?php

namespace App\Services\CentroOperaciones;

use App\Models\CentroOperacionesTicket;
use App\Models\CentroOperacionesTicketFirma;
use App\Models\FuncionarioAcAutorizado;
use App\Models\User;
use App\Support\Cometidos\SimpleQrCode;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class TicketDocumentoService
{
    public function registrarFirmaResolucion(
        CentroOperacionesTicket $ticket,
        User $usuario,
        ?Request $request = null
    ): CentroOperacionesTicketFirma {
        $existente = $ticket->firmaResolucion()->first();
        if ($existente) {
            return $existente;
        }

        $rutNormalizado = $usuario->rut_normalized;
        $funcionario = FuncionarioAcAutorizado::query()
            ->where(function ($query) use ($usuario, $rutNormalizado) {
                $query->where('registered_user_id', $usuario->id);
                if ($rutNormalizado !== '') {
                    $query->orWhere('rut_normalizado', $rutNormalizado);
                }
            })
            ->latest('id')
            ->first();
        $fecha = $ticket->resuelto_en ?: now(config('centro_operaciones.timezone'));
        $token = Str::random(64);
        $rut = $funcionario?->rut_completo ?: ($usuario->rut ?: null);
        $nombre = $funcionario?->nombre_completo ?: ($usuario->display_name ?: 'Usuario '.$usuario->id);
        $rol = Schema::hasTable('model_has_roles') && method_exists($usuario, 'activeRoleName')
            ? $usuario->activeRoleName()
            : null;

        $firma = $ticket->firmaResolucion()->create([
            'user_id' => $usuario->id,
            'funcionario_ac_autorizado_id' => $funcionario?->id,
            'tipo_firma' => 'resolucion',
            'rol_firmante' => $rol,
            'nombre_firmante' => $nombre,
            'rut_firmante' => $rut,
            'cargo_firmante' => $funcionario?->cargo_funcion,
            'dependencia_firmante' => $funcionario?->subdireccion_dependencia,
            'ip_firma' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'fecha_firma' => $fecha,
            'token_firma' => $token,
            'hash_firma' => hash('sha256', implode('|', [
                $ticket->id,
                $ticket->numero,
                'resolucion',
                $rut,
                $fecha->timestamp,
                $token,
            ])),
        ]);

        $this->actualizarHuella($ticket->fresh());

        return $firma;
    }

    public function generarPdf(CentroOperacionesTicket $ticket): string
    {
        $ticket = $this->actualizarHuella($ticket);
        $url = route('centro-operaciones.tickets.verificar', $ticket->codigo_validacion);

        return Pdf::loadView('centro-operaciones.tickets.pdf', [
            'ticket' => $ticket,
            'validacionUrl' => $url,
            'qrDataUri' => SimpleQrCode::dataUri($url, 3, 3),
            'logoDataUri' => $this->logoDataUri(),
            'imagenesPdf' => $this->imagenesPdf($ticket),
        ])->setPaper('a4', 'portrait')->output();
    }

    public function actualizarHuella(CentroOperacionesTicket $ticket): CentroOperacionesTicket
    {
        $ticket = $this->cargar($ticket);
        $hash = $this->huellaActual($ticket);

        if ($ticket->documento_hash !== $hash || ! $ticket->documento_emitido_en) {
            $ticket->forceFill([
                'documento_hash' => $hash,
                'documento_emitido_en' => now(config('centro_operaciones.timezone')),
            ])->save();
        }

        return $this->cargar($ticket->fresh());
    }

    /** @return array{hash_actual:string,integro:bool} */
    public function verificarIntegridad(CentroOperacionesTicket $ticket): array
    {
        $ticket = $this->cargar($ticket);
        $hash = $this->huellaActual($ticket);

        return [
            'hash_actual' => $hash,
            'integro' => $ticket->documento_hash !== null
                && hash_equals($ticket->documento_hash, $hash),
        ];
    }

    private function huellaActual(CentroOperacionesTicket $ticket): string
    {
        $firma = $ticket->firmaResolucion;
        $reporte = $ticket->incidencia->reporte;
        $establecimiento = $ticket->incidencia->establecimiento;
        $contenido = [
            'ticket' => [
                'numero' => $ticket->numero,
                'estado' => $ticket->estado,
                'creado_en' => $ticket->created_at?->toIso8601String(),
                'vence_en' => $ticket->vence_en?->toIso8601String(),
                'resuelto_en' => $ticket->resuelto_en?->toIso8601String(),
                'resolucion' => $ticket->resolucion,
            ],
            'incidencia' => [
                'id' => $ticket->incidencia->id,
                'tipo' => $ticket->incidencia->tipo,
                'modalidad' => $ticket->incidencia->modalidad,
                'severidad' => $ticket->incidencia->severidad,
                'descripcion' => $ticket->incidencia->descripcion,
                'fecha' => $ticket->incidencia->fecha_incidencia?->toDateString(),
            ],
            'reporte' => [
                'id' => $reporte?->id,
                'version' => $reporte?->version,
                'fecha' => $reporte?->fecha_reporte?->toDateString(),
                'enviado_en' => $reporte?->reportado_en?->toIso8601String(),
                'enviado_por' => $reporte?->reportado_por_nombre_visible,
            ],
            'establecimiento' => [
                'id' => $establecimiento?->id,
                'nombre' => $establecimiento?->nombre_establecimiento,
                'rbd' => $establecimiento?->rbd,
            ],
            'asignacion' => [
                'unidad' => $ticket->unidad_departamento,
                'subdireccion' => $ticket->subdireccion_dependencia,
                'responsable' => $ticket->responsable?->nombre_completo,
                'segunda_subdireccion' => $ticket->segunda_subdireccion_responsable,
                'segundo_responsable' => $ticket->segundoResponsable?->nombre_completo,
            ],
            'imagenes' => $ticket->imagenes->map(fn ($imagen) => [
                'id' => $imagen->id,
                'mime_type' => $imagen->mime_type,
                'size_bytes' => $imagen->size_bytes,
                'sha256' => $this->hashImagen($imagen->path),
            ])->all(),
            'firma' => $firma ? [
                'tipo' => $firma->tipo_firma,
                'nombre' => $firma->nombre_firmante,
                'rut' => $firma->rut_firmante,
                'rol' => $firma->rol_firmante,
                'cargo' => $firma->cargo_firmante,
                'dependencia' => $firma->dependencia_firmante,
                'fecha' => $firma->fecha_firma?->toIso8601String(),
                'hash' => $firma->hash_firma,
            ] : null,
        ];

        return hash('sha256', json_encode(
            $contenido,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        ));
    }

    private function cargar(CentroOperacionesTicket $ticket): CentroOperacionesTicket
    {
        return $ticket->loadMissing([
            'incidencia.establecimiento',
            'incidencia.reporte.reportadoPor',
            'responsable',
            'segundoResponsable',
            'creadoPor',
            'resueltoPor',
            'firmaResolucion',
            'imagenes',
        ]);
    }

    /** @return Collection<int, string> */
    private function imagenesPdf(CentroOperacionesTicket $ticket): Collection
    {
        $disco = Storage::disk('local');

        return $ticket->imagenes
            ->map(function ($imagen) use ($disco) {
                if (! $disco->exists($imagen->path)) {
                    return null;
                }

                return 'data:'.$imagen->mime_type.';base64,'.base64_encode($disco->get($imagen->path));
            })
            ->filter()
            ->values();
    }

    private function hashImagen(string $path): ?string
    {
        $disco = Storage::disk('local');

        return $disco->exists($path) ? hash('sha256', $disco->get($path)) : null;
    }

    private function logoDataUri(): ?string
    {
        $path = resource_path('images/logo-andaliencosta.png');
        if (! is_file($path)) {
            return null;
        }

        $contenido = file_get_contents($path);

        return $contenido === false ? null : 'data:image/png;base64,'.base64_encode($contenido);
    }
}
