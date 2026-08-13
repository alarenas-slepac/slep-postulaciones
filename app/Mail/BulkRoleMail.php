<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class BulkRoleMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $recipientName,
        public string $mailSubject,
        public string $messageText,
    ) {
    }

    public function build(): self
    {
        return $this
            ->subject($this->mailSubject)
            ->view('emails.bulk-role-mail', [
                'recipientName' => $this->recipientName,
                'mailSubject' => $this->mailSubject,
                'messageLines' => preg_split("/\r\n|\n|\r/", trim($this->messageText)) ?: [],
            ]);
    }
}
