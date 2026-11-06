<?php

namespace App\Services\Mail;

use App\Mail\QueuedViewMail;
use Illuminate\Contracts\Mail\Mailable;
use Illuminate\Mail\Mailer;

class QueuedMailer extends Mailer
{
    public function send($view, array $data = [], $callback = null)
    {
        $delivery = app(MailDelivery::class);
        if ($delivery->isDelivering()) { return parent::send($view, $data, $callback); }
        if ($view instanceof Mailable) { return $this->sendMailable($view); }
        $message = $this->createMessage();
        if (is_array($view)) {
            foreach ($view as $key => $part) {
                if ($part instanceof \Closure) { $view[$key] = new \Illuminate\Support\HtmlString($this->renderView($part, ['message' => $message] + $data)); }
            }
        }
        if ($callback !== null) { $callback($message); }
        return $delivery->enqueue(new QueuedViewMail($view, $data, $message->getSymfonyMessage()), $this->name);
    }

    protected function sendMailable(Mailable $mailable)
    {
        if (app(MailDelivery::class)->isDelivering()) { return $mailable->mailer($this->name)->send($this); }
        return app(MailDelivery::class)->enqueue($mailable, $this->name);
    }

    public function sendNow($mailable, array $data = [], $callback = null)
    {
        return $this->send($mailable, $data, $callback);
    }

    public function queue($view, $queue = null) { return $this->send($view); }

    public function later($delay, $view, $queue = null)
    {
        return app(MailDelivery::class)->enqueue($view, $this->name, $delay);
    }
}
