<?php

namespace App\Jobs;

use App\Mail\NewChatMessageMail;
use App\Models\Message;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendMessageNotifications implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $messageId;

    public function __construct(int $messageId)
    {
        $this->messageId = $messageId;
    }

    public function handle(): void
    {
        $message = Message::with([
            'user:id,nombres,apellido_paterno,apellido_materno,email',
            'conversation.participants:id,nombres,apellido_paterno,apellido_materno,email',
            'attachments',
        ])->find($this->messageId);

        if (! $message || ! $message->conversation) {
            Log::warning('SendMessageNotifications: mensaje no encontrado', ['message_id' => $this->messageId]);
            return;
        }

        $authorId = (int) $message->user_id;
        $participants = ($message->conversation->participants ?? collect())
            ->where('id', '!=', $authorId)
            ->filter(fn ($user) => filter_var($user->email ?? null, FILTER_VALIDATE_EMAIL))
            ->unique('email')
            ->values();

        foreach ($participants as $recipient) {
            try {
                Mail::to($recipient->email)->send(new NewChatMessageMail($message, $recipient));

                Log::info('Mensaje interno notificado por correo', [
                    'to_user_id' => $recipient->id,
                    'email' => $recipient->email,
                    'message_id' => $message->id,
                ]);
            } catch (\Throwable $ex) {
                Log::warning('No fue posible notificar mensaje interno por correo', [
                    'to_user_id' => $recipient->id,
                    'email' => $recipient->email,
                    'message_id' => $message->id,
                    'error' => $ex->getMessage(),
                ]);
            }
        }
    }
}
