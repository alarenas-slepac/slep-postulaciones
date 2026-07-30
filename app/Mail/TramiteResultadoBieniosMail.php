<?php

namespace App\Mail;

use App\Models\Tramite;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class TramiteResultadoBieniosMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Tramite $tramite, public array $resolutionData = [])
    {
        $this->tramite = $tramite->loadMissing('user');
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Resultado trámite reconocimiento de bienios #' . $this->tramite->id,
        );
    }

    public function content(): Content
    {
        $externalFlow = (bool) $this->tramite->bienios_flujo_externo;

        return new Content(
            view: 'emails.tramites.resultado_bienios_html',
            text: 'emails.tramites.resultado_bienios_text',
            with: [
                'tramite' => $this->tramite,
                'data' => $this->resolutionData,
                'externalFlow' => $externalFlow,
                'summary' => $externalFlow ? [] : $this->tramite->calculo_periodos_resumen,
                'periodos' => $externalFlow ? collect() : $this->tramite->calculo_periodos_flattened_collection,
            ],
        );
    }

    public function attachments(): array
    {
        $attachments = [];

        if ($this->tramite->resolucion_pdf_path
            && Storage::disk('local')->exists($this->tramite->resolucion_pdf_path)) {
            $attachments[] = Attachment::fromStorageDisk('local', $this->tramite->resolucion_pdf_path)
                ->as('RESOLUCION_RECONOCIMIENTO_BIENIOS.pdf')
                ->withMime('application/pdf');
        }

        if ((bool) $this->tramite->bienios_flujo_externo
            && $this->tramite->detalle_calculo_pdf_path
            && Storage::disk('local')->exists($this->tramite->detalle_calculo_pdf_path)) {
            $attachments[] = Attachment::fromStorageDisk('local', $this->tramite->detalle_calculo_pdf_path)
                ->as('DETALLE_COMPUTO_BIENIOS.pdf')
                ->withMime('application/pdf');
        }

        return $attachments;
    }
}
