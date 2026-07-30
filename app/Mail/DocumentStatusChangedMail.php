<?php

namespace App\Mail;

use App\Models\UserDocument;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class DocumentStatusChangedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public UserDocument $document;
    public $user;
    public string $typeLabel;
    public string $status;
    public ?string $reason;

    public function __construct(UserDocument $document)
    {
        $this->document  = $document->loadMissing('user', 'type');
        $this->user      = $this->document->user;
        $this->typeLabel = $this->document->type?->label ?? 'Documento';
        $this->status    = $this->document->status;
        $this->reason    = $this->document->reviewer_comment;
    }

    public function build()
    {
        $estado = $this->status === 'approved'
            ? 'Aprobado'
            : ($this->status === 'rejected' ? 'Rechazado' : 'Pendiente');

        return $this->subject("Estado de documento: {$this->typeLabel} — {$estado}")
            // HTML principal
            ->view('emails.documents.status_changed_html')
            // Fallback de texto
            ->text('emails.documents.status_changed_text')
            // Datos para ambas vistas
            ->with([
                'user'      => $this->user,
                'typeLabel' => $this->typeLabel,
                'status'    => $this->status,
                'estadoLbl' => $estado,
                'reason'    => $this->reason,
                'ctaUrl'    => route('postulant.documents.index'),
                'appName'   => config('brand.platform_name', config('app.name')),
            ]);
    }
}
