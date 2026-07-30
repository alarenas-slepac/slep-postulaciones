<?php

namespace App\Support\Cometidos;

/**
 * Generador QR mínimo, sin dependencias externas, para documentos PDF.
 * Soporta modo byte, corrección L, versiones 1 a 5 y máscara 0.
 */
class SimpleQrCode
{
    private const CAPACITIES = [1 => 19, 2 => 34, 3 => 55, 4 => 80, 5 => 108];
    private const EC_CODEWORDS = [1 => 7, 2 => 10, 3 => 15, 4 => 20, 5 => 26];
    private const ALIGNMENT = [1 => [], 2 => [6, 18], 3 => [6, 22], 4 => [6, 26], 5 => [6, 30]];

    public static function dataUri(string $text, int $scale = 4, int $border = 4): ?string
    {
        try {
            $svg = self::svg($text, $scale, $border);
            return 'data:image/svg+xml;base64,' . base64_encode($svg);
        } catch (\Throwable $e) {
            return null;
        }
    }

    public static function svg(string $text, int $scale = 4, int $border = 4): string
    {
        $bytes = array_values(unpack('C*', $text) ?: []);
        $version = self::selectVersion(count($bytes));
        $matrix = self::matrix($version, $bytes);
        $size = count($matrix);
        $dim = ($size + ($border * 2)) * $scale;
        $rects = [];

        for ($y = 0; $y < $size; $y++) {
            for ($x = 0; $x < $size; $x++) {
                if ($matrix[$y][$x]) {
                    $rects[] = '<rect x="' . (($x + $border) * $scale) . '" y="' . (($y + $border) * $scale) . '" width="' . $scale . '" height="' . $scale . '"/>';
                }
            }
        }

        return '<svg xmlns="http://www.w3.org/2000/svg" width="' . $dim . '" height="' . $dim . '" viewBox="0 0 ' . $dim . ' ' . $dim . '"><rect width="100%" height="100%" fill="#fff"/><g fill="#000">' . implode('', $rects) . '</g></svg>';
    }

    private static function selectVersion(int $byteLength): int
    {
        foreach (self::CAPACITIES as $version => $dataCodewords) {
            if ($byteLength + 2 <= $dataCodewords) {
                return $version;
            }
        }

        throw new \InvalidArgumentException('Texto demasiado largo para QR interno.');
    }

    private static function matrix(int $version, array $bytes): array
    {
        $size = 21 + 4 * ($version - 1);
        $m = array_fill(0, $size, array_fill(0, $size, false));
        $reserved = array_fill(0, $size, array_fill(0, $size, false));

        self::finder($m, $reserved, 0, 0);
        self::finder($m, $reserved, $size - 7, 0);
        self::finder($m, $reserved, 0, $size - 7);
        self::timing($m, $reserved, $size);
        self::alignment($m, $reserved, $version, $size);
        self::darkModule($m, $reserved, $version);
        self::reserveFormat($reserved, $size);

        $data = self::dataCodewords($bytes, self::CAPACITIES[$version]);
        $ecc = self::reedSolomon($data, self::EC_CODEWORDS[$version]);
        $codewords = array_merge($data, $ecc);
        $bits = [];
        foreach ($codewords as $cw) {
            for ($i = 7; $i >= 0; $i--) {
                $bits[] = (($cw >> $i) & 1) === 1;
            }
        }

        self::placeData($m, $reserved, $bits, $size);
        self::formatBits($m, $reserved, $size);

        return $m;
    }

    private static function dataCodewords(array $bytes, int $capacity): array
    {
        $bits = [false, true, false, false]; // modo byte 0100
        $len = count($bytes);
        for ($i = 7; $i >= 0; $i--) {
            $bits[] = (($len >> $i) & 1) === 1;
        }
        foreach ($bytes as $b) {
            for ($i = 7; $i >= 0; $i--) {
                $bits[] = (($b >> $i) & 1) === 1;
            }
        }
        $remaining = $capacity * 8 - count($bits);
        for ($i = 0; $i < min(4, $remaining); $i++) {
            $bits[] = false;
        }
        while (count($bits) % 8 !== 0) {
            $bits[] = false;
        }

        $codewords = [];
        foreach (array_chunk($bits, 8) as $chunk) {
            $value = 0;
            foreach ($chunk as $bit) {
                $value = ($value << 1) | ($bit ? 1 : 0);
            }
            $codewords[] = $value;
        }
        $pad = [0xEC, 0x11];
        $i = 0;
        while (count($codewords) < $capacity) {
            $codewords[] = $pad[$i++ % 2];
        }

        return $codewords;
    }

    private static function finder(array &$m, array &$r, int $x, int $y): void
    {
        for ($dy = -1; $dy <= 7; $dy++) {
            for ($dx = -1; $dx <= 7; $dx++) {
                $xx = $x + $dx;
                $yy = $y + $dy;
                if (! isset($m[$yy][$xx])) {
                    continue;
                }
                $r[$yy][$xx] = true;
                $inside = $dx >= 0 && $dx <= 6 && $dy >= 0 && $dy <= 6;
                $dark = $inside && ($dx === 0 || $dx === 6 || $dy === 0 || $dy === 6 || ($dx >= 2 && $dx <= 4 && $dy >= 2 && $dy <= 4));
                $m[$yy][$xx] = $dark;
            }
        }
    }

    private static function timing(array &$m, array &$r, int $size): void
    {
        for ($i = 8; $i < $size - 8; $i++) {
            $dark = $i % 2 === 0;
            $m[6][$i] = $dark;
            $m[$i][6] = $dark;
            $r[6][$i] = true;
            $r[$i][6] = true;
        }
    }

    private static function alignment(array &$m, array &$r, int $version, int $size): void
    {
        foreach (self::ALIGNMENT[$version] as $cy) {
            foreach (self::ALIGNMENT[$version] as $cx) {
                if (($cx === 6 && $cy === 6) || ($cx === 6 && $cy === $size - 7) || ($cx === $size - 7 && $cy === 6)) {
                    continue;
                }
                for ($dy = -2; $dy <= 2; $dy++) {
                    for ($dx = -2; $dx <= 2; $dx++) {
                        $xx = $cx + $dx;
                        $yy = $cy + $dy;
                        $r[$yy][$xx] = true;
                        $m[$yy][$xx] = max(abs($dx), abs($dy)) !== 1;
                    }
                }
            }
        }
    }

    private static function darkModule(array &$m, array &$r, int $version): void
    {
        $y = 4 * $version + 9;
        $m[$y][8] = true;
        $r[$y][8] = true;
    }

    private static function reserveFormat(array &$r, int $size): void
    {
        for ($i = 0; $i < 9; $i++) {
            if ($i !== 6) {
                $r[8][$i] = true;
                $r[$i][8] = true;
            }
        }
        for ($i = 0; $i < 8; $i++) {
            $r[8][$size - 1 - $i] = true;
            $r[$size - 1 - $i][8] = true;
        }
    }

    private static function placeData(array &$m, array &$r, array $bits, int $size): void
    {
        $bit = 0;
        $upward = true;
        for ($right = $size - 1; $right >= 1; $right -= 2) {
            if ($right === 6) {
                $right--;
            }
            for ($vert = 0; $vert < $size; $vert++) {
                $y = $upward ? $size - 1 - $vert : $vert;
                for ($j = 0; $j < 2; $j++) {
                    $x = $right - $j;
                    if ($r[$y][$x]) {
                        continue;
                    }
                    $value = $bits[$bit++] ?? false;
                    if (($x + $y) % 2 === 0) { // máscara 0
                        $value = ! $value;
                    }
                    $m[$y][$x] = $value;
                }
            }
            $upward = ! $upward;
        }
    }

    private static function formatBits(array &$m, array &$r, int $size): void
    {
        $format = 0x77C4; // EC L, máscara 0, BCH enmascarado.

        // El formato QR se escribe desde el bit más significativo al menos significativo
        // sobre las 15 posiciones reservadas. La versión previa lo escribía invertido,
        // generando un QR visible pero no decodificable por lectores estándar.
        $bits = [];
        for ($i = 14; $i >= 0; $i--) {
            $bits[] = (($format >> $i) & 1) === 1;
        }

        $positions = [];
        for ($i = 0; $i <= 5; $i++) {
            $positions[] = [8, $i];
        }
        $positions[] = [8, 7];
        $positions[] = [8, 8];
        $positions[] = [7, 8];
        for ($i = 5; $i >= 0; $i--) {
            $positions[] = [$i, 8];
        }

        foreach ($positions as $index => [$y, $x]) {
            $m[$y][$x] = $bits[$index];
        }

        $copyPositions = [];
        for ($i = 0; $i < 8; $i++) {
            $copyPositions[] = [$size - 1 - $i, 8];
        }
        for ($i = 8; $i < 15; $i++) {
            $copyPositions[] = [8, $size - 15 + $i];
        }

        foreach ($copyPositions as $index => [$y, $x]) {
            $m[$y][$x] = $bits[$index];
        }
    }

    private static function reedSolomon(array $data, int $ecCount): array
    {
        $gen = self::rsGenerator($ecCount);
        $res = array_fill(0, $ecCount, 0);
        foreach ($data as $byte) {
            $factor = $byte ^ $res[0];
            array_shift($res);
            $res[] = 0;
            for ($i = 0; $i < $ecCount; $i++) {
                $res[$i] ^= self::gfMul($gen[$i], $factor);
            }
        }
        return $res;
    }

    private static function rsGenerator(int $degree): array
    {
        $poly = [1];
        for ($i = 0; $i < $degree; $i++) {
            $next = array_fill(0, count($poly) + 1, 0);
            foreach ($poly as $j => $coef) {
                $next[$j] ^= self::gfMul($coef, 1);
                $next[$j + 1] ^= self::gfMul($coef, self::gfPow(2, $i));
            }
            $poly = $next;
        }
        array_shift($poly);
        return $poly;
    }

    private static function gfPow(int $x, int $power): int
    {
        $result = 1;
        for ($i = 0; $i < $power; $i++) {
            $result = self::gfMul($result, $x);
        }
        return $result;
    }

    private static function gfMul(int $x, int $y): int
    {
        $z = 0;
        for ($i = 7; $i >= 0; $i--) {
            $z = (($z << 1) ^ (($z >> 7) * 0x11D)) & 0xFF;
            if ((($y >> $i) & 1) !== 0) {
                $z ^= $x;
            }
        }
        return $z;
    }
}
