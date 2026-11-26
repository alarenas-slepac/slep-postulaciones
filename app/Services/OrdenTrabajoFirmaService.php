<?php

namespace App\Services;

use App\Models\SolicitudReemplazoConfiguracion;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class OrdenTrabajoFirmaService
{
    public const PERFILES = ['titular' => 'Titular', 'subrogante' => 'Subrogante'];

    public function titularInstitucional(): array
    {
        return [
            'nombre' => 'MAKARENA PAREDES AGUILERA',
            'cargo' => 'SUBDIRECTORA DE GESTION Y DESARROLLO DE LAS PERSONAS',
            'archivo' => null,
        ];
    }

    public function configuracion(?SolicitudReemplazoConfiguracion $registro = null): array
    {
        $registro ??= SolicitudReemplazoConfiguracion::query()
            ->where('clave', SolicitudReemplazoConfiguracion::FIRMA_ORDEN_TRABAJO)->first();
        $guardada = json_decode($registro?->valor ?? 'null', true);

        return $guardada ?? [
            'vigente' => 'titular',
            'titular' => $this->titularInstitucional(),
            'subrogante' => ['nombre' => '', 'cargo' => '', 'archivo' => null],
        ];
    }

    public function vigente(): array
    {
        $configuracion = $this->configuracion();

        return $configuracion[$configuracion['vigente']];
    }

    public function paraPdf(array $firmante, ?UploadedFile $nuevaFirma = null): array
    {
        $timbre = $this->imagenInstitucional('timbre.png');
        if ($nuevaFirma || $firmante['archivo']) {
            $contenido = $nuevaFirma ? $nuevaFirma->get() : Storage::disk('local')->get($firmante['archivo']);
            $imagen = is_string($contenido) ? @getimagesizefromstring($contenido) : false;
            if (! $imagen || ! in_array($imagen['mime'], ['image/png', 'image/jpeg'], true)) {
                throw new RuntimeException('La imagen de firma de la orden de trabajo no está disponible.');
            }

            return $firmante + [
                'firma' => 'data:'.$imagen['mime'].';base64,'.base64_encode($contenido),
                'timbre' => $timbre,
                'combinada' => null,
                'personalizada' => true,
            ];
        }

        $firma = $this->imagenInstitucional('firma.png');

        return $firmante + [
            'firma' => $firma,
            'timbre' => $timbre,
            'combinada' => $firma ? null : $this->imagenInstitucional('firma-makarena.png'),
            'personalizada' => false,
        ];
    }

    private function imagenInstitucional(string $nombre): ?string
    {
        $ruta = public_path('branding/'.$nombre);

        return is_file($ruta) ? 'data:image/png;base64,'.base64_encode(file_get_contents($ruta)) : null;
    }
}
