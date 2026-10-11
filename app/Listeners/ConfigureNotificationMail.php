<?php

namespace App\Listeners;

use App\Services\Notifications\NotificationEventCatalog;
use App\Services\Notifications\NotificationEventPolicy;
use Illuminate\Mail\Events\MessageSending;

class ConfigureNotificationMail
{
    public function __construct(private NotificationEventCatalog $catalog, private NotificationEventPolicy $policy) {}

    public function configure(MessageSending $sending): ?bool
    {
        $message = $sending->message;
        $subject = $message->getSubject() ?? '';
        $key = $this->catalog->resolveMail($sending->data, $subject);
        if (!$key) return null;
        $event = $this->catalog->find($key);
        if ($event['protected'] ?? false) return null;
        app(\App\Services\Notifications\MailWhatsAppNotifier::class)->notify($key, $event, $sending);
        if (!$this->policy->enabled($key, 'mail')) return false;
        $setting = $this->policy->setting($key, 'mail');
        if (!$setting) return null;
        if ($setting->subject_template) {
            $value = $this->policy->text($setting->subject_template, '', $subject, $event['name']);
            $message->subject(mb_substr(preg_replace('/[\r\n\x00-\x1f\x7f]+/', ' ', $value), 0, 200));
        }
        if ($setting->body_template) {
            [$before, $after] = explode(NotificationEventPolicy::ORIGINAL, $setting->body_template, 2);
            $before = $this->policy->text($before, '', $subject, $event['name']);
            $after = $this->policy->text($after, '', $subject, $event['name']);
            if ($html = $message->getHtmlBody()) {
                $block = fn ($text) => trim($text) === '' ? '' : '<div style="max-width:720px;margin:16px auto;padding:16px;background:#ffffff;color:#334155;font-family:Arial,sans-serif;line-height:1.6;">'.nl2br(e($text)).'</div>';
                $prefix = $block($before); $suffix = $block($after);
                $html = preg_match('/<body\b[^>]*>/i', $html)
                    ? preg_replace_callback('/<body\b[^>]*>/i', fn ($m) => $m[0].$prefix, $html, 1) : $prefix.$html;
                $html = stripos($html, '</body>') !== false ? preg_replace_callback('/<\/body>/i', fn () => $suffix.'</body>', $html, 1) : $html.$suffix;
                $message->html($html);
            }
            if ($text = $message->getTextBody()) $message->text(trim($before."\n\n".$text."\n\n".$after));
        }
        // Copias explícitas del administrador, sin reemplazar propietario/responsable ni repetir To/CC/BCC.
        $known = collect(array_merge($message->getTo(), $message->getCc(), $message->getBcc()))->map(fn ($a) => strtolower($a->getAddress()))->all();
        foreach ($setting->additional_emails ?? [] as $email) {
            if (filter_var($email, FILTER_VALIDATE_EMAIL) && !in_array(strtolower($email), $known, true)) {
                $message->addBcc($email); $known[] = strtolower($email);
            }
        }
        return null;
    }
}
