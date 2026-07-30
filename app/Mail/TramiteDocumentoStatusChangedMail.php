<?php

namespace App\Mail;

use App\Models\TramiteDocumento;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class TramiteDocumentoStatusChangedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public TramiteDocumento $documento;
    public $tramite;
    public $user;
    public string $tipoDocumentoLabel;
    public string $estadoRevision;
    public ?string $observacion;

    public function __construct(TramiteDocumento $documento)
    {
        $this->documento = $documento->loadMissing('tramite.user');
        $this->tramite = $this->documento->tramite;
        $this->user = $this->tramite?->user;
        $this->tipoDocumentoLabel = $this->documento->tipo_documento_label;
        $this->estadoRevision = (string) $this->documento->estado_revision;
        $this->observacion = $this->documento->revision_observacion;
    }

    public function build()
    {
        $estado = $this->estadoRevision === 'aprobado'
            ? 'Aprobado'
            : ($this->estadoRevision === 'rechazado' ? 'Rechazado' : 'Pendiente');

        return $this->subject("Estado de documento de trámite: {$this->tipoDocumentoLabel} — {$estado}")
            ->view('emails.tramites.document_status_changed_html')
            ->text('emails.tramites.document_status_changed_text')
            ->with([
                'user' => $this->user,
                'tramite' => $this->tramite,
                'documento' => $this->documento,
                'tipoDocumentoLabel' => $this->tipoDocumentoLabel,
                'estadoRevision' => $this->estadoRevision,
                'estadoLbl' => $estado,
                'observacion' => $this->observacion,
                'ctaUrl' => $this->tramite ? route('tramites.show', $this->tramite) : route('tramites.index'),
                'appName' => config('brand.platform_name', config('app.name')),
            ]);
    }
}
