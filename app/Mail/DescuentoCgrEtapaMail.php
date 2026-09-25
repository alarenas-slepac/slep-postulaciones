<?php

namespace App\Mail;

use App\Models\DescuentoCgr;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class DescuentoCgrEtapaMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public DescuentoCgr $descuento, public string $evento) {}

    public function build(): self
    {
        return $this->subject($this->evento === 'finanzas' ? 'Descuento CGR para registro de Finanzas' : 'Descuento CGR para gestión de Auditoría')
            ->view('emails.descuentos-cgr.etapa');
    }
}
