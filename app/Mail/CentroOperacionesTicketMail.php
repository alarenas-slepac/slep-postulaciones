<?php

namespace App\Mail;

use App\Models\CentroOperacionesTicket;
use App\Services\CentroOperaciones\TicketPdfService;
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
        $pdf = app(TicketPdfService::class)->render($this->ticket);
        $asunto = $this->evento === 'escalamiento' ? 'Ticket vencido' : 'Nuevo ticket asignado';

        return $this->subject("{$asunto} · {$this->ticket->numero}")
            ->view('emails.centro-operaciones-ticket')
            ->attachData($pdf->output(), "{$this->ticket->numero}.pdf", ['mime' => 'application/pdf']);
    }
}
