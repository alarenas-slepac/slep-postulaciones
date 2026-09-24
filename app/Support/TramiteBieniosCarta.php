<?php

namespace App\Support;

use Illuminate\Support\Str;

class TramiteBieniosCarta
{
    public static function esAaee(string $estatuto, string $escalafon): bool
    {
        $texto = strtoupper(Str::ascii(trim($estatuto . ' ' . $escalafon)));

        foreach (['ASIST', 'AAEE', 'A.A.E.E', 'PARADOCENTE', 'LEY 19.464', 'LEY 19464', 'LEY 21.109'] as $indicador) {
            if (str_contains($texto, $indicador)) {
                return true;
            }
        }

        if (str_contains($texto, 'DOC') || str_contains($texto, 'PROFES') || str_contains($texto, 'EDUCADOR')) {
            return false;
        }

        foreach (['CODIGO DEL TRABAJO', 'AUXILIAR', 'ADMINISTRATIVO', 'TECNICO'] as $indicador) {
            if (str_contains($texto, $indicador)) {
                return true;
            }
        }

        return false;
    }

    public static function configKey(string $estatuto, string $escalafon): string
    {
        return self::esAaee($estatuto, $escalafon)
            ? 'template_relative_path_aaee'
            : 'template_relative_path';
    }

    public static function estamentoLabel(string $estatuto, string $escalafon): string
    {
        return self::esAaee($estatuto, $escalafon) ? 'AAEE' : 'Docente';
    }
}
