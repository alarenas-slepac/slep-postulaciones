<?php

namespace App\Notifications\Auth;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use App\Services\Mail\QueuesMailNotification;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class WelcomeUser extends Notification implements ShouldQueueAfterCommit, ShouldBeEncrypted
{
    use Queueable, QueuesMailNotification;

    public function via($notifiable): array
    {
        return $this->mailChannels($notifiable);
    }

    public function shouldSend($notifiable, $channel): bool { return $this->sameMailRecipient($notifiable); }

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
