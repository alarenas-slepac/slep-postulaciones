<?php

namespace App\Notifications\Auth;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use App\Services\Mail\QueuesMailNotification;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\URL;

class CustomVerifyEmail extends Notification implements ShouldQueueAfterCommit, ShouldBeEncrypted
{
    use Queueable, QueuesMailNotification;

    public function via($notifiable): array
    {
        return $this->mailChannels($notifiable);
    }

    public function shouldSend($notifiable, $channel): bool
    {
        return !$notifiable->hasVerifiedEmail() && $this->sameMailRecipient($notifiable);
    }

    protected function verificationUrl($notifiable): string
    {
        return URL::temporarySignedRoute(
            'verification.verify',
            Carbon::now()->addMinutes((int) Config::get('auth.verification.expire', 60)),
            [
                'id'   => $notifiable->getKey(),
                'hash' => sha1($notifiable->getEmailForVerification()),
            ]
        );
    }

    public function toMail($notifiable): MailMessage
    {
        $url = $this->verificationUrl($notifiable);

        return (new MailMessage)
            ->subject('Verifica tu correo')
            ->greeting('Hola ' . ($notifiable->nombres ?? $notifiable->name ?? ''))
            ->line('Para activar tu cuenta, por favor verifica tu dirección de correo electrónico.')
            ->action('Verificar correo', $url)
            ->line('Si no creaste esta cuenta, ignora este mensaje.')
            ->salutation('Saludos cordiales, ' . config('brand.platform_name', config('app.name')));
    }
}
