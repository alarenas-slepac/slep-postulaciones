<?php

namespace App\Services;

use App\Models\SolicitudReemplazo;
use Illuminate\Support\Facades\Storage;

class ResolucionDocenteDocxService
{
    public function generateAndStore(SolicitudReemplazo $solicitud): string
    {
        $templatePath = $this->templatePath();
        $tmp = tempnam(sys_get_temp_dir(), 'res_') . '.docx';
        copy($templatePath, $tmp);
        $zip = new \ZipArchive();
        if ($zip->open($tmp) !== true) throw new \RuntimeException('No se pudo abrir la plantilla DOCX.');
        $values = $this->values($solicitud);
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $part = $zip->getNameIndex($i);
            if (!is_string($part) || !preg_match('#^word/(document|header\d+|footer\d+)\.xml$#', $part)) continue;
            $xml = $zip->getFromName($part);
            if ($xml === false) continue;
            foreach ($values as $token => $value) $xml = str_replace($token, htmlspecialchars($value, ENT_XML1 | ENT_COMPAT, 'UTF-8'), $xml);
            $dom = new \DOMDocument(); $dom->preserveWhiteSpace = false;
            if (@$dom->loadXML($xml)) {
                $xp = new \DOMXPath($dom); $xp->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
                foreach ($xp->query('//w:p') as $p) {
                    $nodes = $xp->query('.//w:t', $p); $plain = ''; foreach ($nodes as $n) $plain .= $n->textContent;
                    $new = strtr($plain, $values); if ($new === $plain || !$nodes->length) continue;
                    $nodes->item(0)->nodeValue = $new; for ($j = 1; $j < $nodes->length; $j++) $nodes->item($j)->nodeValue = '';
                }
                $xml = $dom->saveXML();
            }
            $zip->addFromString($part, $xml);
        }
        $zip->close();
        $dir = "resoluciones-docentes/solicitudes/{$solicitud->id}";
        $path = "{$dir}/RESOLUCION_DOCENTE_{$solicitud->numero_solicitud}.docx";
        Storage::disk('local')->put($path, file_get_contents($tmp)); @unlink($tmp);
        return $path;
    }

    private function templatePath(): string
    {
        $relative = 'templates/resolucion_docente_reemplazo.docx';
        $versionedPath = resource_path('templates/resolucion_docente_reemplazo.docx');
        $disk = Storage::disk('local');

        if (is_file($versionedPath)) {
            $versionedHash = hash_file('sha256', $versionedPath);
            $storedHash = $disk->exists($relative)
                ? hash_file('sha256', $disk->path($relative))
                : false;

            if (!is_string($versionedHash)) {
                throw new \RuntimeException('No se pudo leer la plantilla de resolución docente.');
            }

            if (!is_string($storedHash) || !hash_equals($versionedHash, $storedHash)) {
                $contents = file_get_contents($versionedPath);
                if ($contents === false || !$disk->put($relative, $contents)) {
                    throw new \RuntimeException('No se pudo actualizar la plantilla de resolución docente.');
                }
            }
        }

        if (!$disk->exists($relative)) {
            throw new \RuntimeException('No existe la plantilla de resolución docente.');
        }

        return $disk->path($relative);
    }

    private function values(SolicitudReemplazo $s): array
    {
        $s->loadMissing(['establecimiento', 'funcionarioTitular', 'areaDesempeno', 'postulante.user', 'derivadaA', 'jornadas']);
        $user = $s->postulante?->user;
        $tit = $s->funcionarioTitular;
        $full = fn ($x) => mb_strtoupper(trim((string) ($x?->full_name ?? $x?->nombre ?? '')), 'UTF-8');
        $rut = fn ($x) => $this->rut($x?->rut ?? $x);
        $basic = (float) $s->jornadas->sum('reemplazo_basica');
        $media = (float) $s->jornadas->sum('reemplazo_media');
        $sub = [];
        foreach ($s->jornadas as $j) {
            $fin = strtoupper(trim((string) ($j->financiamiento ?? 'GENERAL')));
            $label = str_contains($fin, 'SEP') ? 'SEP' : (str_contains($fin, 'PIE') ? 'PIE' : 'Subvención General');
            $value = (float) ($j->reemplazo_total ?? 0); if ($value > 0) $sub[$label] = ($sub[$label] ?? 0) + $value;
        }
        $subText = implode('; ', array_map(fn ($label, $hours) => mb_strtoupper($label, 'UTF-8') . ': ' . rtrim(rtrim(number_format($hours, 2, ',', ''), '0'), ',') . ' HORAS', array_keys($sub), $sub));
        $derivada = $s->derivadaA;
        $initials = mb_strtolower(implode('', array_map(
            fn ($value) => mb_substr(trim((string) $value), 0, 1),
            [$derivada?->nombres, $derivada?->apellido_paterno, $derivada?->apellido_materno]
        )), 'UTF-8');
        $establecimiento = mb_strtoupper((string) ($s->establecimiento?->nombre_establecimiento ?? $s->establecimiento?->nombre ?? ''), 'UTF-8');
        return [
            '[NOMBRE ESTABLECIMIENTO]' => $establecimiento,
            '{NOMBRE ESTABLECIMIENTO}' => $establecimiento,
            '{NOMBRE  ESTABLECIMIENTO}' => $establecimiento,
            '[NOMBRE REEMPLAZO]' => $full($user), '[RUT REEMPLAZO]' => $rut($user),
            '[ÁREA DE DESEMPEÑO]' => mb_strtoupper((string) ($s->areaDesempeno?->nombre ?? ''), 'UTF-8'),
            '[ESTABLECIMIENTO]' => $establecimiento,
            '[HORAS TOTALES]' => (string) $s->jornadas->sum('reemplazo_total'), '[TOTAL HORAS BASICA]' => (string) $basic, '[TOTAL HORAS MEDIA]' => (string) $media,
            '[NOMBRE TITULAR]' => $full($tit), '[RUT TITULAR]' => $rut($tit),
            '[FECHA INICIO DE REEMPLAZO]' => optional($s->fecha_inicio_trabajo ?? $s->fecha_inicio)->format('d/m/Y'),
            '[FECHA TÉRMINO DE REEMPLAZO]' => optional($s->fecha_termino)->format('d/m/Y'), '[SUBVENCIONES]' => $subText,
            '[INICIALES Derivada a {PRMER NOMBRE+APELLIDO PATERNO+APELLIDO MATERNO}]' => $initials,
        ];
    }

    private function rut($value): string
    {
        $v = strtoupper(preg_replace('/[^0-9K]/', '', (string) $value)); if ($v === '') return '';
        $body = substr($v, 0, -1); $dv = substr($v, -1); $body = ltrim($body, '0');
        return number_format((int) $body, 0, '', '.') . '-' . $dv;
    }
}
