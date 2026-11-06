<?php

namespace App\Console\Commands;

use App\Services\Mail\MailDelivery;
use Illuminate\Console\Command;

class MailDeliveryControl extends Command
{
    protected $signature = 'sga-mail:control {accion=estado : estado, pausar o reanudar}';
    protected $description = 'Pausa o reanuda la entrega de correo sin detener la recepción de nuevos mensajes en cola.';

    public function handle(MailDelivery $delivery): int
    {
        $action = $this->argument('accion');
        if (!in_array($action, ['estado', 'pausar', 'reanudar'], true)) {
            $this->error('Acción válida: estado, pausar o reanudar.'); return self::FAILURE;
        }
        if ($action !== 'estado') { $delivery->pause($action === 'pausar'); }
        $this->info($delivery->paused() ? 'Entrega pausada. Los correos se conservan en la cola sga-mail.' : 'Entrega habilitada mediante la cola sga-mail.');
        if ($action === 'estado') {
            if (\Illuminate\Support\Facades\Schema::hasTable('mail_jobs')) {
                $this->line('Trabajos pendientes: '.\Illuminate\Support\Facades\DB::table('mail_jobs')->where('queue', MailDelivery::QUEUE)->count());
            } else { $this->warn('Falta aplicar la migración de mail_jobs.'); }
        }
        return self::SUCCESS;
    }
}
