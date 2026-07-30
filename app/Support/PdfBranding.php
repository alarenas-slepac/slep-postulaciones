<?php

namespace App\Support;

class PdfBranding
{
    public static function profileBrand(): array
    {
        $slepLogo = self::findReadableFile([
            resource_path('branding/pdf/logo-andaliencosta.png'),
            base_path('resources/branding/pdf/logo-andaliencosta.png'),
            self::documentRootPath('branding/logo-andaliencosta.png'),
            self::documentRootPath('branding/logo-andalien-costa.png'),
            base_path('branding/logo-andaliencosta.png'),
            base_path('public_html/branding/logo-andaliencosta.png'),
            base_path('../public_html/branding/logo-andaliencosta.png'),
            public_path('branding/logo-andaliencosta.png'),
            public_path('logo-andaliencosta.png'),
        ]);

        $sgaLogo = self::findReadableFile([
            resource_path('branding/pdf/01_logo_principal.png'),
            base_path('resources/branding/pdf/01_logo_principal.png'),
            self::documentRootPath('branding/01_logo_principal.png'),
            self::documentRootPath('branding/04_lockup_horizontal.png'),
            base_path('branding/01_logo_principal.png'),
            base_path('public_html/branding/01_logo_principal.png'),
            base_path('../public_html/branding/01_logo_principal.png'),
            public_path('branding/01_logo_principal.png'),
            public_path('01_logo_principal.png'),
        ]);

        return [
            'logo'          => $sgaLogo,
            'slep_logo'     => $slepLogo,
            'sga_logo'      => $sgaLogo,
            'slep_logo_src' => self::toDataUri($slepLogo),
            'sga_logo_src'  => self::toDataUri($sgaLogo),
            'primary'       => '#0b5ed7',
            'muted'         => '#6c757d',
        ];
    }

    private static function documentRootPath(string $relative): ?string
    {
        $root = $_SERVER['DOCUMENT_ROOT'] ?? null;
        if (!is_string($root) || trim($root) === '') {
            return null;
        }

        return rtrim($root, '/\\') . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relative);
    }

    private static function findReadableFile(array $candidates): ?string
    {
        foreach ($candidates as $candidate) {
            if (!is_string($candidate) || trim($candidate) === '') {
                continue;
            }

            if (is_file($candidate) && is_readable($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private static function toDataUri(?string $path): ?string
    {
        if (!is_string($path) || $path === '' || !is_file($path) || !is_readable($path)) {
            return null;
        }

        $contents = @file_get_contents($path);
        if ($contents === false || $contents === '') {
            return null;
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $mime = 'image/png';
        if ($extension === 'jpg' || $extension === 'jpeg') {
            $mime = 'image/jpeg';
        } elseif ($extension === 'svg') {
            $mime = 'image/svg+xml';
        } elseif ($extension === 'gif') {
            $mime = 'image/gif';
        } elseif ($extension === 'webp') {
            $mime = 'image/webp';
        }

        return 'data:' . $mime . ';base64,' . base64_encode($contents);
    }
}
