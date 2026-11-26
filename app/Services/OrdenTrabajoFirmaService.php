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
            'archivo_timbre' => null,
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
            'subrogante' => ['nombre' => '', 'cargo' => '', 'archivo' => null, 'archivo_timbre' => null],
        ];
    }

    public function vigente(): array
    {
        $configuracion = $this->configuracion();

        return $configuracion[$configuracion['vigente']];
    }

    public function paraPdf(array $firmante, ?UploadedFile $nuevaFirma = null, ?UploadedFile $nuevoTimbre = null): array
    {
        $firmaPersonalizada = $nuevaFirma || $firmante['archivo'];
        $archivoTimbre = $firmante['archivo_timbre'] ?? null;
        $timbrePersonalizado = $nuevoTimbre || $archivoTimbre;
        $firma = $firmaPersonalizada
            ? $this->imagenCargada($firmante['archivo'], $nuevaFirma, 'firma')
            : $this->imagenInstitucional('firma.png');
        $timbre = $timbrePersonalizado
            ? $this->imagenCargada($archivoTimbre, $nuevoTimbre, 'timbre')
            : $this->imagenInstitucional('timbre.png');

        return $firmante + [
            'firma' => $firma,
            'timbre' => $timbre,
            'combinada' => $firma || $timbrePersonalizado ? null : $this->imagenInstitucional('firma-makarena.png'),
            'personalizada' => $firmaPersonalizada,
            'timbrePersonalizado' => $timbrePersonalizado,
        ];
    }

    private function imagenCargada(?string $ruta, ?UploadedFile $nuevaImagen, string $tipo): string
    {
        $contenido = $nuevaImagen ? $nuevaImagen->get() : Storage::disk('local')->get($ruta);
        $imagen = is_string($contenido) ? @getimagesizefromstring($contenido) : false;
        if (! $imagen || ! in_array($imagen['mime'], ['image/png', 'image/jpeg'], true)) {
            throw new RuntimeException("La imagen de {$tipo} de la orden de trabajo no está disponible.");
        }

        return 'data:'.$imagen['mime'].';base64,'.base64_encode($contenido);
    }

    private function imagenInstitucional(string $nombre): ?string
    {
        $ruta = public_path('branding/'.$nombre);

        return is_file($ruta) ? 'data:image/png;base64,'.base64_encode(file_get_contents($ruta)) : null;
    }
}
