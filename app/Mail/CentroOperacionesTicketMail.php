<?php

namespace App\Mail;

use App\Models\CentroOperacionesTicket;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class CentroOperacionesTicketMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public CentroOperacionesTicket $ticket, public string $evento)
    {
        $this->afterCommit();
    }

    public function build(): self
    {
        $this->ticket->loadMissing(['incidencia.establecimiento', 'responsable', 'segundoResponsable']);
        $pdf = Pdf::loadView('centro-operaciones.tickets.pdf', ['ticket' => $this->ticket]);
        $asunto = $this->evento === 'escalamiento' ? 'Ticket vencido' : 'Nuevo ticket asignado';

        return $this->subject("{$asunto} · {$this->ticket->numero}")
            ->view('emails.centro-operaciones-ticket')
            ->attachData($pdf->output(), "{$this->ticket->numero}.pdf", ['mime' => 'application/pdf']);
    }
}
