<?php

namespace App\Jobs;

use App\Mail\BulkRoleMail;
use App\Models\User;
use App\Support\NotificationAudit;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendBulkRoleMail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public int $userId,
        public string $subject,
        public string $body,
        public int $triggeredByUserId,
        public array $roles = [],
    ) {
    }

    public function handle(): void
    {
        $user = User::query()->find($this->userId);

        $hasSelectedRole = $user
            && (empty($this->roles) || $user->hasAnyRole($this->roles));

        if (! $user || ! $hasSelectedRole || ! $user->email_verified_at || ! filter_var($user->email, FILTER_VALIDATE_EMAIL)) {
            Log::warning('Correo masivo por rol omitido: destinatario sin rol seleccionado, inválido o sin correo verificado.', [
                'user_id' => $this->userId,
            ]);
            return;
        }

        NotificationAudit::sendMail(
            $user->email,
            new BulkRoleMail($user->display_name, $this->subject, $this->body),
            [
                'event_key' => 'admin.bulk_role_mail',
                'description' => 'Correo masivo enviado por selección de rol',
                'recipient_name' => $user->display_name,
                'subject' => $this->subject,
                'notifiable' => $user,
                'triggered_by_user_id' => $this->triggeredByUserId,
                'context' => [
                    'roles' => array_values($this->roles),
                    'bulk_role_mail' => true,
                ],
            ]
        );
    }
}
