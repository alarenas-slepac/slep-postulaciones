<?php

namespace App\Notifications\Auth;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Config;

class CustomResetPassword extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(protected string $token) {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $email = method_exists($notifiable, 'getEmailForPasswordReset')
            ? $notifiable->getEmailForPasswordReset()
            : $notifiable->email;

        $url = route('password.reset', ['token' => $this->token]) . '?email=' . urlencode((string)$email);

        $minutes = (int) Config::get('auth.passwords.' . Config::get('auth.defaults.passwords') . '.expire', 60);

        return (new MailMessage)
            ->subject('Restablecer contraseña')
            ->greeting('Hola ' . ($notifiable->nombres ?? $notifiable->name ?? ''))
            ->line('Recibimos una solicitud para restablecer tu contraseña.')
            ->action('Restablecer contraseña', $url)
            ->line("Este enlace vencerá en {$minutes} minutos.")
            ->line('Si no solicitaste este cambio, no es necesario realizar ninguna acción.')
            ->salutation('Saludos cordiales, ' . config('brand.platform_name', config('app.name')));
    }
}
