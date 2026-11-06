<?php

namespace App\Services\Mail;

use Illuminate\Mail\MailManager;

class QueuedMailManager extends MailManager
{
    public function build($config)
    {
        $mailer = new QueuedMailer($config['name'] ?? 'ondemand', $this->app['view'],
            $this->createSymfonyTransport($config), $this->app['events']);
        if ($this->app->bound('queue')) { $mailer->setQueue($this->app['queue']); }
        return $mailer;
    }
}
