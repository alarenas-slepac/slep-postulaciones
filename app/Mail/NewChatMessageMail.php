<?php

namespace App\Mail;

use App\Models\Message;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

class NewChatMessageMail extends Mailable
{
    use Queueable, SerializesModels;

    public Message $chatMessage;
    public ?User $recipient;

    public function __construct(Message $message, ?User $recipient = null)
    {
        $this->chatMessage = $message;
        $this->recipient = $recipient;
    }

    public function build()
    {
        $sender = $this->chatMessage->user;
        $senderName = trim(($sender->nombres ?? '') . ' ' . ($sender->apellido_paterno ?? '') . ' ' . ($sender->apellido_materno ?? ''));
        $senderName = $senderName !== '' ? $senderName : ($sender->email ?? 'Usuario');
        $subject = 'Nuevo mensaje de ' . $senderName;

        return $this->subject(Str::limit($subject, 120, ''))
            ->view('emails.chat.new-message')
            ->with([
                'chatMessage' => $this->chatMessage,
                'recipient' => $this->recipient,
                'senderName' => $senderName,
            ]);
    }
}
