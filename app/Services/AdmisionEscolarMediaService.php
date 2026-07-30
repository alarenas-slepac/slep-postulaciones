<?php

namespace App\Services;

use App\Models\Establecimiento;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Laravel\Facades\Image;
use RuntimeException;
use Throwable;

class AdmisionEscolarMediaService
{
    public function storeLogo(UploadedFile $file, Establecimiento $establecimiento): string
    {
        return $this->storeImage($file, $establecimiento, 'logo');
    }

    public function storeDirectorPhoto(UploadedFile $file, Establecimiento $establecimiento): string
    {
        return $this->storeImage($file, $establecimiento, 'director');
    }

    public function storeGalleryImage(UploadedFile $file, Establecimiento $establecimiento): string
    {
        return $this->storeImage($file, $establecimiento, 'galeria');
    }

    public function delete(?string $path): void
    {
        if (! $path) {
            return;
        }

        $disk = Storage::disk(config('admision.media_disk', 'public'));
        if ($disk->exists($path)) {
            $disk->delete($path);
        }
    }

    private function storeImage(
        UploadedFile $file,
        Establecimiento $establecimiento,
        string $kind
    ): string {
        $settings = $this->optimizationSettings($kind);
        $this->assertOptimizable($file);

        $diskName = (string) config('admision.media_disk', 'public');
        $baseDirectory = trim((string) config('admision.media_directory', 'admision-establecimientos'), '/');
        $directory = $baseDirectory . '/' . $establecimiento->id . '/' . $kind;
        $path = $directory . '/' . Str::uuid() . '.webp';
        $errors = [];

        if (class_exists(\Imagick::class)) {
            try {
                $this->optimizeWithImagick(
                    $file,
                    $diskName,
                    $path,
                    $settings['max_width'],
                    $settings['max_height'],
                    $settings['quality']
                );

                return $path;
            } catch (Throwable $exception) {
                $errors[] = 'Imagick: ' . $exception->getMessage();
                $this->delete($path);
            }
        }

        try {
            $this->optimizeWithIntervention(
                $file,
                $diskName,
                $path,
                $settings['max_width'],
                $settings['max_height'],
                $settings['quality']
            );

            return $path;
        } catch (Throwable $exception) {
            $errors[] = 'Intervention Image: ' . $exception->getMessage();
            $this->delete($path);
        }

        Log::warning('No fue posible optimizar una imagen de Admision Escolar.', [
            'establecimiento_id' => $establecimiento->id,
            'tipo' => $kind,
            'nombre_original' => $file->getClientOriginalName(),
            'tamano_bytes' => $file->getSize(),
            'errores' => $errors,
        ]);

        throw new RuntimeException(
            'No fue posible optimizar la imagen. Verifica que el servidor tenga habilitado Imagick o GD con soporte WebP.'
        );
    }

    private function optimizationSettings(string $kind): array
    {
        $defaults = [
            'logo' => ['max_width' => 1600, 'max_height' => 1600, 'quality' => 88],
            'director' => ['max_width' => 1600, 'max_height' => 1600, 'quality' => 84],
            'galeria' => ['max_width' => 2400, 'max_height' => 1800, 'quality' => 82],
        ];

        $configured = (array) config('admision.optimizacion.' . $kind, []);
        $settings = array_merge($defaults[$kind] ?? $defaults['galeria'], $configured);

        return [
            'max_width' => max(320, (int) ($settings['max_width'] ?? 2400)),
            'max_height' => max(320, (int) ($settings['max_height'] ?? 1800)),
            'quality' => min(95, max(55, (int) ($settings['quality'] ?? 82))),
        ];
    }

    private function assertOptimizable(UploadedFile $file): void
    {
        if (! $file->isValid() || ! is_file($file->getPathname())) {
            throw new RuntimeException('La imagen no se recibio correctamente. Intenta cargarla nuevamente.');
        }

        $size = @getimagesize($file->getPathname());
        if (! is_array($size) || empty($size[0]) || empty($size[1])) {
            throw new RuntimeException('El archivo no contiene una imagen legible.');
        }

        $width = (int) $size[0];
        $height = (int) $size[1];
        $maxMegapixels = max(1, (int) config('admision.max_megapixeles', 80));
        $megapixels = ($width * $height) / 1000000;

        if ($megapixels > $maxMegapixels) {
            throw new RuntimeException(
                "La imagen tiene demasiada resolucion ({$width}x{$height}). El maximo permitido es {$maxMegapixels} megapixeles."
            );
        }
    }

    private function optimizeWithImagick(
        UploadedFile $file,
        string $diskName,
        string $path,
        int $maxWidth,
        int $maxHeight,
        int $quality
    ): void {
        $temporaryDirectory = storage_path('app/tmp/admision-imagick');
        if (! is_dir($temporaryDirectory)
            && ! mkdir($temporaryDirectory, 0755, true)
            && ! is_dir($temporaryDirectory)) {
            throw new RuntimeException('No fue posible crear el directorio temporal de optimizacion.');
        }

        $temporaryOutput = $temporaryDirectory . '/' . Str::uuid() . '.webp';
        $image = new \Imagick();

        try {
            $image->setResourceLimit(
                \Imagick::RESOURCETYPE_MEMORY,
                max(32, (int) config('admision.imagick.memory_mb', 96))
            );
            $image->setResourceLimit(
                \Imagick::RESOURCETYPE_MAP,
                max(64, (int) config('admision.imagick.map_mb', 256))
            );
            $image->setResourceLimit(
                \Imagick::RESOURCETYPE_DISK,
                max(256, (int) config('admision.imagick.disk_mb', 1024))
            );
            $image->setOption('registry:temporary-path', $temporaryDirectory);
            $image->readImage($file->getPathname() . '[0]');

            if (method_exists($image, 'autoOrientImage')) {
                $image->autoOrientImage();
            }

            if ($image->getImageColorspace() !== \Imagick::COLORSPACE_SRGB) {
                $image->transformImageColorspace(\Imagick::COLORSPACE_SRGB);
            }

            if ($image->getImageWidth() > $maxWidth || $image->getImageHeight() > $maxHeight) {
                $image->thumbnailImage($maxWidth, $maxHeight, true, true);
            }

            $image->setImagePage(0, 0, 0, 0);
            $image->stripImage();
            $image->setImageFormat('webp');
            $image->setOption('webp:method', '6');
            $image->setOption('webp:thread-level', '1');
            $image->setImageCompressionQuality($quality);

            if (! $image->writeImage($temporaryOutput) || ! is_file($temporaryOutput)) {
                throw new RuntimeException('Imagick no genero el archivo optimizado.');
            }

            $stream = fopen($temporaryOutput, 'rb');
            if ($stream === false) {
                throw new RuntimeException('No fue posible leer el archivo optimizado.');
            }

            try {
                $written = Storage::disk($diskName)->put($path, $stream);
            } finally {
                fclose($stream);
            }

            if (! $written) {
                throw new RuntimeException('No fue posible guardar la imagen optimizada.');
            }
        } finally {
            $image->clear();
            $image->destroy();

            if (is_file($temporaryOutput)) {
                @unlink($temporaryOutput);
            }
        }
    }

    private function optimizeWithIntervention(
        UploadedFile $file,
        string $diskName,
        string $path,
        int $maxWidth,
        int $maxHeight,
        int $quality
    ): void {
        $image = Image::read($file->getPathname())
            ->orient()
            ->scaleDown(width: $maxWidth, height: $maxHeight);

        $encoded = $image->toWebp($quality);
        $written = Storage::disk($diskName)->put($path, (string) $encoded);

        unset($encoded, $image);
        gc_collect_cycles();

        if (! $written) {
            throw new RuntimeException('No fue posible guardar la imagen procesada.');
        }
    }
}
