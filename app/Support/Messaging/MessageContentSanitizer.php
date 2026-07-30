<?php

namespace App\Support\Messaging;

use Illuminate\Support\Str;

class MessageContentSanitizer
{
    public static function clean(?string $html): string
    {
        $html = trim((string) $html);

        if ($html === '') {
            return '';
        }

        $html = str_replace(["\x00"], '', $html);

        if ($html === strip_tags($html)) {
            return nl2br(e($html), false);
        }

        $html = preg_replace('/<\s*(script|style|iframe|object|embed|form|input|button|meta|link)[^>]*>.*?<\s*\/\s*\1\s*>/is', '', $html) ?? $html;
        $html = preg_replace('/<\s*(script|style|iframe|object|embed|form|input|button|meta|link)[^>]*\/?\s*>/is', '', $html) ?? $html;
        $html = strip_tags($html, '<p><br><strong><b><em><i><u><s><strike><ul><ol><li><blockquote><span><div><a><font>');
        $html = preg_replace('/\s+on[a-z0-9_:-]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html) ?? $html;
        $html = preg_replace('/\s+(src|srcset)\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html) ?? $html;

        $html = preg_replace_callback('/<([a-z0-9]+)([^>]*)>/i', function (array $match): string {
            $tag = strtolower($match[1]);
            $attrs = (string) ($match[2] ?? '');

            if ($tag === 'br') {
                return '<br>';
            }

            $allowedAttrs = [];

            if ($tag === 'a') {
                $href = self::attributeValue($attrs, 'href');
                if ($href && self::safeUrl($href)) {
                    $allowedAttrs[] = 'href="' . e($href) . '"';
                    $allowedAttrs[] = 'target="_blank"';
                    $allowedAttrs[] = 'rel="noopener noreferrer"';
                }
            }

            if (in_array($tag, ['p', 'div', 'span', 'font', 'blockquote', 'li'], true)) {
                $style = self::sanitizeStyle(self::attributeValue($attrs, 'style'));
                if ($style !== '') {
                    $allowedAttrs[] = 'style="' . e($style) . '"';
                }
            }

            if ($tag === 'font') {
                $face = self::safeFontFamily(self::attributeValue($attrs, 'face'));
                $color = self::safeColor(self::attributeValue($attrs, 'color'));
                $size = self::safeFontSize(self::attributeValue($attrs, 'size'));

                if ($face !== '') {
                    $allowedAttrs[] = 'face="' . e($face) . '"';
                }
                if ($color !== '') {
                    $allowedAttrs[] = 'color="' . e($color) . '"';
                }
                if ($size !== '') {
                    $allowedAttrs[] = 'size="' . e($size) . '"';
                }
            }

            return '<' . $tag . (count($allowedAttrs) ? ' ' . implode(' ', $allowedAttrs) : '') . '>';
        }, $html) ?? $html;

        $html = preg_replace('/\s{2,}/', ' ', $html) ?? $html;

        return trim($html);
    }

    public static function plain(?string $html, int $limit = 0): string
    {
        $plain = html_entity_decode(strip_tags((string) $html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $plain = str_replace("\xc2\xa0", ' ', $plain);
        $plain = preg_replace('/\s+/', ' ', $plain) ?? $plain;
        $plain = trim($plain);

        return $limit > 0 ? Str::limit($plain, $limit) : $plain;
    }

    private static function attributeValue(string $attrs, string $name): string
    {
        if (preg_match('/\s' . preg_quote($name, '/') . '\s*=\s*("([^"]*)"|\'([^\']*)\'|([^\s>]+))/i', $attrs, $match)) {
            return trim((string) ($match[2] ?? $match[3] ?? $match[4] ?? ''));
        }

        return '';
    }

    private static function safeUrl(string $url): bool
    {
        $url = trim($url);
        if ($url === '') {
            return false;
        }

        $lower = strtolower($url);

        return str_starts_with($lower, 'http://')
            || str_starts_with($lower, 'https://')
            || str_starts_with($lower, 'mailto:')
            || str_starts_with($lower, '/');
    }

    private static function sanitizeStyle(string $style): string
    {
        if ($style === '') {
            return '';
        }

        $allowed = [];
        foreach (explode(';', $style) as $declaration) {
            if (! str_contains($declaration, ':')) {
                continue;
            }

            [$property, $value] = array_map('trim', explode(':', $declaration, 2));
            $property = strtolower($property);
            $value = trim($value);

            if ($value === '' || preg_match('/(expression|url|javascript|data:|<|>)/i', $value)) {
                continue;
            }

            if (in_array($property, ['color', 'background-color'], true)) {
                $safe = self::safeColor($value);
            } elseif ($property === 'font-family') {
                $safe = self::safeFontFamily($value);
            } elseif ($property === 'font-size') {
                $safe = self::safeFontSize($value);
            } elseif ($property === 'font-weight' && preg_match('/^(normal|bold|[1-9]00)$/i', $value)) {
                $safe = strtolower($value);
            } elseif ($property === 'font-style' && preg_match('/^(normal|italic|oblique)$/i', $value)) {
                $safe = strtolower($value);
            } elseif ($property === 'text-decoration' && preg_match('/^(none|underline|line-through)$/i', $value)) {
                $safe = strtolower($value);
            } else {
                $safe = '';
            }

            if ($safe !== '') {
                $allowed[] = $property . ': ' . $safe;
            }
        }

        return implode('; ', $allowed);
    }

    private static function safeColor(string $value): string
    {
        $value = trim($value);

        if (preg_match('/^#[0-9a-f]{3,8}$/i', $value)
            || preg_match('/^rgba?\(\s*[0-9]{1,3}\s*,\s*[0-9]{1,3}\s*,\s*[0-9]{1,3}(\s*,\s*(0|1|0?\.\d+))?\s*\)$/i', $value)
            || preg_match('/^[a-z]{3,24}$/i', $value)) {
            return $value;
        }

        return '';
    }

    private static function safeFontFamily(string $value): string
    {
        $value = trim($value, " \t\n\r\0\x0B\"'");

        return preg_match('/^[a-z0-9 ,._\-\"\']{1,120}$/i', $value) ? $value : '';
    }

    private static function safeFontSize(string $value): string
    {
        $value = trim($value);

        if (preg_match('/^([1-7]|[0-9]{1,2}(px|pt)|[0-9]{1,2}(\.[0-9])?(em|rem|%)|small|medium|large|x-large)$/i', $value)) {
            return $value;
        }

        return '';
    }
}
