<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class BaseMailable extends Mailable
{
    use Queueable, SerializesModels;

    public string $subjectText;
    public ?string $greeting;
    /** @var array<int,string> */
    public array $lines = [];
    public ?string $actionText;
    public ?string $actionUrl;
    /** @var array<int,string> */
    public array $outroLines = [];
    public ?string $salutation;

    /**
     * @param array<int,string> $lines
     * @param array<int,string> $outroLines
     */
    public function __construct(
        string $subjectText,
        ?string $greeting = null,
        array $lines = [],
        ?string $actionText = null,
        ?string $actionUrl = null,
        array $outroLines = [],
        ?string $salutation = null
    ) {
        $this->subjectText = $subjectText;
        $this->greeting    = $greeting;
        $this->lines       = $lines;
        $this->actionText  = $actionText;
        $this->actionUrl   = $actionUrl;
        $this->outroLines  = $outroLines;
        $this->salutation  = $salutation;
    }

    public function build(): self
    {
        return $this
            ->subject($this->subjectText)
            ->view('emails.shared.message', [
                'greeting'   => $this->greeting,
                'lines'      => $this->lines,
                'actionText' => $this->actionText,
                'actionUrl'  => $this->actionUrl,
                'outroLines' => $this->outroLines,
                'salutation' => $this->salutation,
            ]);
    }
}
