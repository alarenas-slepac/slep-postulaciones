<?php

namespace App\Services\Notifications;

use App\Models\CometidoNotificacionConfiguracion;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class NotificationEventCatalog
{
    public function all(): array
    {
        $events = [];
        foreach (config('notification_events.mailables', []) as [$key, $module, $name, $recipients]) {
            $events[$key] = $this->event($module, $name, $recipients, ['mail']);
        }
        foreach (config('notification_events.whatsapp', []) as $key => [$name, $roles]) {
            $events[$key] ??= $this->event($key === 'admin.test' ? 'Administración' : 'Reemplazos', $name,
                $roles ? 'Responsable de la etapa, con ficha AC activa y preferencia WhatsApp' : 'Reemplazante definitivo: teléfono principal del perfil', []);
            $events[$key]['channels'][] = 'whatsapp';
            $events[$key]['whatsapp_roles'] = $roles;
            $events[$key]['whatsapp_recipients'] = $roles ? 'Responsables de la etapa: '.implode(', ', $roles).'. Conserva ficha AC, preferencia y asignación.'
                : 'Reemplazante definitivo: teléfono principal del perfil';
        }
        $events['admin.test']['recipients'] = 'Número ingresado expresamente por el administrador';
        $events['admin.test']['whatsapp_recipients'] = $events['admin.test']['recipients'];
        foreach (CometidoNotificacionConfiguracion::catalogoProceso() as $key => $item) {
            $events['cometidos.'.$key] = $this->event('Cometidos', $item['nombre'], $item['descripcion'], ['mail'])
                + ['cometido_key' => $key, 'subjects' => $item['asuntos'] ?? []];
        }
        foreach ([
            'cometidos.otros' => ['Cometidos', 'Otros avisos de etapas de Cometidos', 'Destinatario calculado por el trámite'],
            'centro_operaciones.asignacion' => ['Centro de Operaciones', 'Ticket asignado o reasignado', 'Responsable y segundo responsable'],
            'centro_operaciones.escalamiento' => ['Centro de Operaciones', 'Ticket vencido: escalamiento', 'Responsables y unidad de escalamiento'],
            'descuentos_cgr.finanzas' => ['Remuneraciones', 'Descuento CGR: Finanzas', 'Usuarios DAF configurados en el proceso'],
            'descuentos_cgr.auditoria' => ['Remuneraciones', 'Descuento CGR: Auditoría', 'Usuarios de Auditoría configurados en el proceso'],
            'critical_exception' => ['Sistema', 'Excepción crítica', 'Destinatarios técnicos configurados en el servidor'],
            'failed_job' => ['Sistema', 'Trabajo de cola fallido', 'Destinatarios técnicos configurados en el servidor'],
            'system.generic_mail' => ['Sistema', 'Correo base genérico', 'Destinatario indicado por el emisor'],
            'auth.welcome' => ['Acceso', 'Bienvenida (sin invocaciones actuales)', 'Cuenta registrada'],
        ] as $key => [$module, $name, $recipients]) {
            $events[$key] = $this->event($module, $name, $recipients, ['mail']);
        }
        foreach (['avance' => 'Avanza de etapa', 'no_avanza' => 'No avanza de etapa', 'desierto' => 'Proceso desierto', 'cerrado' => 'Proceso cerrado'] as $type => $name) {
            $events['gestion.bolsa-trabajo.etapas.'.$type] = $this->event('Bolsa de Trabajo', $name, 'Postulante y contacto autorizado del establecimiento', ['mail']);
        }
        foreach (['pendiente' => 'Solicitud pendiente', 'aprobada' => 'Solicitud aprobada', 'rechazada' => 'Solicitud rechazada', 'anulacion' => 'Sala liberada por anulación', 'edicion_horario' => 'Cambio de horario'] as $type => $name) {
            $events['agendamientos.'.$type] = $this->event('Agendamientos', $name, in_array($type, ['aprobada', 'rechazada']) ? 'Solicitante' : 'Administradores del recurso', ['mail']);
        }
        foreach (['auth.email_verification' => 'Verificación de correo', 'auth.password_reset' => 'Recuperación de contraseña'] as $key => $name) {
            $events[$key] = $this->event('Acceso', $name, 'Cuenta que solicita acceso; enlaces personales protegidos', ['mail']) + ['protected' => true];
        }
        foreach (config('notification_events.notifications', []) as [$key, $module, $name, $recipients, $protected]) {
            $events[$key] = $this->event($module, $name, $recipients, ['mail']) + ['protected' => $protected];
        }
        foreach (config('notification_events.events', []) as $key => [$module, $name, $recipients]) {
            $events[$key] = $this->event($module, $name, $recipients, ['mail']);
        }
        // Incluso una clase aún sin catalogar aparece para revisión; nunca activa WhatsApp por inferencia.
        foreach ($this->mailClasses() as $class) {
            if ($this->registeredClass($class)) continue;
            $key = $this->discoveredKey($class);
            $events[$key] = $this->event('Pendientes de catalogar', Str::headline(class_basename($class)), 'Destinatario original del emisor', ['mail'])
                + ['discovered' => true, 'class' => $class];
        }
        foreach ($events as &$definition) {
            if (!in_array('mail', $definition['channels'], true) || ($definition['protected'] ?? false)) continue;
            if (!in_array('whatsapp', $definition['channels'], true)) {
                $definition['channels'][] = 'whatsapp';
                $definition['whatsapp_from_mail'] = true;
                $definition['whatsapp_default'] = false;
                $definition['whatsapp_recipients'] = 'Destinatarios originales del correo con cuenta verificada y teléfono válido en ficha AC vigente o perfil. La selección sólo restringe ese grupo.';
            }
        }
        unset($definition);
        uasort($events, fn ($a, $b) => [$a['module'], $a['name']] <=> [$b['module'], $b['name']]);
        return $events;
    }

    public function find(string $key): ?array { return $this->all()[$key] ?? null; }

    public function resolveMail(array $data, string $subject = ''): ?string
    {
        $class = $data['__laravel_mailable'] ?? $data['__laravel_notification'] ?? '';
        $notifications = config('notification_events.notifications', []);
        if (isset($notifications[$class]) && ($notifications[$class][4] ?? false)) return $notifications[$class][0];
        $explicit = $data['sga_event_key'] ?? null;
        // Estas clases comparten una clave antigua de auditoría; distinguir su etapa real.
        if ($class === \App\Mail\BolsaTrabajoEtapaPostulacionMail::class) {
            return 'gestion.bolsa-trabajo.etapas.'.(in_array($data['tipo'] ?? '', ['avance', 'no_avanza', 'desierto', 'cerrado']) ? $data['tipo'] : 'cerrado');
        }
        if ($class === \App\Mail\DescuentoCgrEtapaMail::class) return 'descuentos_cgr.'.(($data['evento'] ?? '') === 'finanzas' ? 'finanzas' : 'auditoria');
        if ($class === \App\Mail\CentroOperacionesTicketMail::class) return 'centro_operaciones.'.(($data['evento'] ?? '') === 'escalamiento' ? 'escalamiento' : 'asignacion');
        if ($explicit && $this->find($explicit)) return $explicit;
        $map = config('notification_events.mailables', []);
        if (isset($map[$class])) return $map[$class][0];
        if (isset($notifications[$class])) return $notifications[$class][0];
        if ($class === \App\Mail\CometidoFuncionarioNotificationMail::class) {
            $key = $this->cometidoKey($subject);
            return $key ? 'cometidos.'.$key : 'cometidos.otros';
        }
        if ($class === \App\Mail\BaseMailable::class) {
            $key = $this->cometidoKey($subject);
            return $key ? 'cometidos.'.$key : 'system.generic_mail';
        }
        if (isset($data['agendamiento']) && $data['agendamiento'] instanceof \App\Models\AgendamientoRecurso) {
            $type = isset($data['aprobada']) ? ($data['aprobada'] ? 'aprobada' : 'rechazada') : ($data['tipo'] ?? 'pendiente');
            return $this->find('agendamientos.'.$type) ? 'agendamientos.'.$type : null;
        }
        return $class && in_array($class, $this->mailClasses(), true) ? $this->discoveredKey($class) : null;
    }

    public function mailClasses(): array
    {
        $classes = [];
        foreach (['Mail' => \Illuminate\Mail\Mailable::class, 'Notifications' => \Illuminate\Notifications\Notification::class] as $directory => $base) {
            foreach (File::allFiles(app_path($directory)) as $file) {
                if ($file->getExtension() !== 'php') continue;
                $class = 'App\\'.$directory.'\\'.str_replace('/', '\\', substr(str_replace('\\', '/', $file->getRelativePathname()), 0, -4));
                if (in_array($class, config('notification_events.non_mail_notifications', []), true) || !class_exists($class) || !is_subclass_of($class, $base)) continue;
                if (!(new \ReflectionClass($class))->isAbstract()) $classes[] = $class;
            }
        }
        return $classes;
    }

    public function registeredClass(string $class): bool
    {
        return isset(config('notification_events.mailables', [])[$class]) || isset(config('notification_events.notifications', [])[$class])
            || in_array($class, [\App\Mail\BaseMailable::class, \App\Mail\CometidoFuncionarioNotificationMail::class,
                \App\Mail\CentroOperacionesTicketMail::class, \App\Mail\BolsaTrabajoEtapaPostulacionMail::class, \App\Mail\DescuentoCgrEtapaMail::class], true);
    }

    public function discoveredKey(string $class): string { return 'uncatalogued.'.substr(hash('sha256', $class), 0, 40); }

    private function event(string $module, string $name, string $recipients, array $channels): array
    {
        return compact('module', 'name', 'recipients', 'channels');
    }

    private function cometidoKey(string $subject): ?string
    {
        // El cierre agrega el nombre del solicitante al asunto; la clave no depende de ese dato.
        return str_starts_with($subject, 'Cometido funcionario cerrado - ')
            ? 'establecimiento_cierre_cometido' : CometidoNotificacionConfiguracion::claveParaAsunto($subject);
    }
}
