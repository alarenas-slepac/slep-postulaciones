<?php
namespace App\Mail;
use App\Models\SolicitudReemplazo;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
class ResolucionDocenteFirmadaEnviada extends Mailable
{
    use Queueable, SerializesModels;
    public function __construct(public SolicitudReemplazo $solicitud, public ?string $recipientLabel = null) {}
    public function build()
    {
        return $this->subject("Resolución docente firmada reemplazo {$this->solicitud->numero_solicitud}")
            ->view('emails.resolucion-docente-firmada-enviada', ['s' => $this->solicitud, 'recipientLabel' => $this->recipientLabel])
            ->attachFromStorageDisk('local', $this->solicitud->resolucion_docente_firmada_pdf_path, 'RESOLUCION_DOCENTE_FIRMADA.pdf', ['mime' => 'application/pdf']);
    }
}
