<?php

namespace App\Services\CentroOperaciones;

use App\Models\CentroOperacionesTicket;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class TicketPdfService
{
    public function render(CentroOperacionesTicket $ticket)
    {
        $ticket->loadMissing([
            'incidencia.establecimiento',
            'responsable',
            'imagenes',
        ]);

        $imagenesPdf = $ticket->imagenes
            ->map(function ($imagen) {
                if (! Storage::disk('local')->exists($imagen->path)) {
                    return null;
                }

                $contenido = Storage::disk('local')->get($imagen->path);

                return 'data:'.$imagen->mime_type.';base64,'.base64_encode($contenido);
            })
            ->filter()
            ->values();

        return Pdf::loadView('centro-operaciones.tickets.pdf', [
            'ticket' => $ticket,
            'imagenesPdf' => $imagenesPdf,
        ])->setPaper('letter');
    }
}
