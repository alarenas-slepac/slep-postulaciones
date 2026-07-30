<?php

namespace App\Notifications\Auth;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class WelcomeUser extends Notification implements ShouldQueue
{
    use Queueable;

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $dashboardUrl = url('/');

        return (new MailMessage)
            ->subject('¡Bienvenido/a a ' . config('brand.platform_name', config('app.name')) . '!')
            ->greeting('Hola ' . ($notifiable->nombres ?? $notifiable->name ?? ''))
            ->line('Tu registro se ha completado correctamente.')
            ->line('Si aún no has verificado tu correo, por favor revisa tu bandeja de entrada.')
            ->action('Ir al sitio', $dashboardUrl)
            ->salutation('Saludos cordiales, ' . config('brand.platform_name', config('app.name')));
    }
}
