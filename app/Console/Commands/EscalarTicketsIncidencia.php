<?php

namespace App\Console\Commands;

use App\Services\CentroOperaciones\TicketService;
use Illuminate\Console\Command;

class EscalarTicketsIncidencia extends Command
{
    protected $signature = 'incidencias:escalar-tickets';
    protected $description = 'Notifica a la subdirección los tickets de incidencia cuyo plazo venció';

    public function handle(TicketService $tickets): int
    {
        $this->info($tickets->escalarVencidos().' ticket(s) escalado(s).');
        return self::SUCCESS;
    }
}
