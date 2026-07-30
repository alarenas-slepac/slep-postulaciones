<?php

namespace App\Support;

use App\Models\NotificationDispatchLog;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Mail\Mailable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Throwable;

class NotificationAudit
{
    public static function sendMail(string $to, Mailable $mailable, array $meta = []): void
    {
        $cc = collect((array) ($meta['cc'] ?? []))
            ->map(fn ($email) => trim((string) $email))
            ->filter(fn ($email) => $email !== '')
            ->unique()
            ->values()
            ->all();

        $log = self::createBaseLog(array_merge($meta, [
            'channel' => 'mail',
            'status' => 'pending',
            'recipient_email' => trim($to),
            'mailable_class' => get_class($mailable),
        ]));

        try {
            $mailer = Mail::to($to);
            if (!empty($cc)) {
                $mailer->cc($cc);
            }
            $mailer->send($mailable);

            if ($log) {
                $log->update([
                    'status' => 'sent',
                    'sent_at' => now(),
                ]);
            }
        } catch (Throwable $e) {
            if ($log) {
                $log->update([
                    'status' => 'failed',
                    'failed_at' => now(),
                    'error_message' => self::truncate($e->getMessage(), 4000),
                ]);
            }

            throw $e;
        }
    }

    public static function dispatchNotification(User $user, Notification $notification, array $meta = []): void
    {
        $queued = $notification instanceof ShouldQueue;

        $log = self::createBaseLog(array_merge($meta, [
            'channel' => 'mail',
            'status' => $queued ? 'queued' : 'pending',
            'recipient_email' => (string) ($user->email ?? ''),
            'recipient_name' => $user->display_name ?? $user->full_name ?? $user->email,
            'notification_class' => get_class($notification),
            'notifiable' => $user,
        ]));

        try {
            $user->notify($notification);

            if ($log && !$queued) {
                $log->update([
                    'status' => 'sent',
                    'sent_at' => now(),
                ]);
            }
        } catch (Throwable $e) {
            if ($log) {
                $log->update([
                    'status' => 'failed',
                    'failed_at' => now(),
                    'error_message' => self::truncate($e->getMessage(), 4000),
                ]);
            }

            throw $e;
        }
    }

    protected static function createBaseLog(array $meta): ?NotificationDispatchLog
    {
        if (!Schema::hasTable('notification_dispatch_logs')) {
            return null;
        }

        $related = $meta['related'] ?? null;
        $notifiable = $meta['notifiable'] ?? null;

        return NotificationDispatchLog::create([
            'channel' => $meta['channel'] ?? 'mail',
            'status' => $meta['status'] ?? 'pending',
            'event_key' => $meta['event_key'] ?? null,
            'description' => $meta['description'] ?? null,
            'recipient_email' => $meta['recipient_email'] ?? null,
            'recipient_name' => $meta['recipient_name'] ?? null,
            'subject' => $meta['subject'] ?? null,
            'mailable_class' => $meta['mailable_class'] ?? null,
            'notification_class' => $meta['notification_class'] ?? null,
            'notifiable_type' => $notifiable instanceof Model ? $notifiable->getMorphClass() : null,
            'notifiable_id' => $notifiable instanceof Model ? $notifiable->getKey() : null,
            'related_type' => $related instanceof Model ? $related->getMorphClass() : null,
            'related_id' => $related instanceof Model ? $related->getKey() : null,
            'triggered_by_user_id' => $meta['triggered_by_user_id'] ?? Auth::id(),
            'context' => self::normalizeContext($meta['context'] ?? []),
            'sent_at' => $meta['status'] === 'sent' ? now() : null,
        ]);
    }

    protected static function normalizeContext(array $context): array
    {
        $request = request();

        if ($request) {
            $context = array_merge([
                'route_name' => optional($request->route())->getName(),
                'url' => $request->fullUrl(),
                'ip' => $request->ip(),
            ], $context);
        }

        return $context;
    }

    protected static function truncate(?string $value, int $limit): ?string
    {
        if ($value === null) {
            return null;
        }

        return mb_strlen($value) > $limit
            ? mb_substr($value, 0, $limit - 1) . '…'
            : $value;
    }
}
