<?php

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

if (!function_exists('cl_chile_timezone')) {
    function cl_chile_timezone(): string
    {
        return (string) config('app.display_timezone', 'America/Santiago');
    }
}

if (!function_exists('cl_carbon')) {
    function cl_carbon($value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof CarbonInterface) {
            return Carbon::instance($value)->copy();
        }

        if ($value instanceof DateTimeInterface) {
            return Carbon::instance($value);
        }

        return Carbon::parse($value, 'UTC');
    }
}

if (!function_exists('cl_datetime')) {
    function cl_datetime($value, string $format = 'd-m-Y H:i', string $fallback = '—'): string
    {
        $date = cl_carbon($value);

        if (!$date) {
            return $fallback;
        }

        return $date->setTimezone(cl_chile_timezone())->format($format);
    }
}

if (!function_exists('cl_date')) {
    function cl_date($value, string $format = 'd-m-Y', string $fallback = '—'): string
    {
        return cl_datetime($value, $format, $fallback);
    }
}


if (!function_exists('cl_plain_date')) {
    function cl_plain_date($value, string $format = 'd-m-Y', string $fallback = '—'): string
    {
        if ($value === null || $value === '') {
            return $fallback;
        }

        if ($value instanceof CarbonInterface) {
            return Carbon::instance($value)->copy()->format($format);
        }

        if ($value instanceof DateTimeInterface) {
            return Carbon::instance($value)->format($format);
        }

        try {
            return Carbon::parse($value)->format($format);
        } catch (Throwable $e) {
            return $fallback;
        }
    }
}

if (!function_exists('cl_time')) {
    function cl_time($value, string $format = 'H:i', string $fallback = '—'): string
    {
        return cl_datetime($value, $format, $fallback);
    }
}
