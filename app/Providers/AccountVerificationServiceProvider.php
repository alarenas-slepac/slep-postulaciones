<?php

namespace App\Providers;

use App\Support\ChileanMobilePhone;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AccountVerificationServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        RateLimiter::for('account-whatsapp-send', function (Request $request): array {
            $phone = ChileanMobilePhone::normalize($request->input('whatsapp_phone')) ?? 'invalid';
            return [
                Limit::perMinute(1)->by('wa-send-user:'.$request->user()?->id),
                Limit::perMinute(1)->by('wa-send-phone:'.hash('sha256', $phone)),
                Limit::perHour(10)->by('wa-send-hour:'.$request->user()?->id),
                Limit::perHour(10)->by('wa-send-phone-hour:'.hash('sha256', $phone)),
                Limit::perHour(300)->by('wa-send-ip:'.hash('sha256', (string) $request->ip())),
            ];
        });
        RateLimiter::for('account-whatsapp-confirm', fn (Request $request) => [
            Limit::perMinute(10)->by('wa-confirm-user:'.$request->user()?->id),
            Limit::perHour(600)->by('wa-confirm-ip:'.hash('sha256', (string) $request->ip())),
        ]);
    }
}
