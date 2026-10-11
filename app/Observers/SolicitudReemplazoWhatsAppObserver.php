<?php

namespace App\Observers;

use App\Models\SolicitudReemplazo;
use App\Services\WhatsApp\ReemplazanteWhatsAppNotifier;

class SolicitudReemplazoWhatsAppObserver
{
    public function updated(SolicitudReemplazo $solicitud): void
    {
        // La OT se guarda antes de esta transición, dentro de la transacción del trámite.
        // Regenerar un PDF o retornar desde cierre no constituye otra aceptación.
        if ($solicitud->wasChanged('estado') && $solicitud->getOriginal('estado') === 'derivada_slep'
            && $solicitud->estado === 'aceptada') {
            app(ReemplazanteWhatsAppNotifier::class)->notify($solicitud);
        }
    }
}
