<?php

namespace App\Services\CentroOperaciones;

use App\Models\CentroOperacionesTicket;
use App\Models\CentroOperacionesTicketImagen;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Intervention\Image\Laravel\Facades\Image;
use RuntimeException;
use Throwable;

class TicketImagenService
{
    /**
     * @param  array<int, UploadedFile>  $archivos
     * @return Collection<int, CentroOperacionesTicketImagen>
     */
    public function guardar(CentroOperacionesTicket $ticket, User $usuario, array $archivos): Collection
    {
        $paths = [];

        try {
            return DB::transaction(function () use ($ticket, $usuario, $archivos, &$paths) {
                CentroOperacionesTicket::query()->whereKey($ticket->id)->lockForUpdate()->firstOrFail();
                $maximo = (int) config('centro_operaciones.ticket_imagenes.maximo', 10);

                if ($ticket->imagenes()->count() + count($archivos) > $maximo) {
                    throw ValidationException::withMessages([
                        'imagenes' => 'El ticket admite un máximo acumulado de '.$maximo.' imágenes.',
                    ]);
                }

                return collect($archivos)->map(function (UploadedFile $archivo) use ($ticket, $usuario, &$paths) {
                    $datos = $this->procesar($archivo, $ticket);
                    $paths[] = $datos['path'];

                    return $ticket->imagenes()->create([
                        'path' => $datos['path'],
                        'mime_type' => 'image/jpeg',
                        'size_bytes' => $datos['size_bytes'],
                        'subida_por_id' => $usuario->id,
                    ]);
                })->values();
            });
        } catch (Throwable $exception) {
            if ($paths !== []) {
                Storage::disk('local')->delete($paths);
            }

            throw $exception;
        }
    }

    /**
     * @return array{path: string, size_bytes: int}
     */
    private function procesar(UploadedFile $archivo, CentroOperacionesTicket $ticket): array
    {
        $dimensiones = @getimagesize($archivo->getPathname());
        if (! is_array($dimensiones)) {
            throw ValidationException::withMessages([
                'imagenes' => 'Uno de los archivos no contiene una imagen legible.',
            ]);
        }

        $megapixeles = ((int) $dimensiones[0] * (int) $dimensiones[1]) / 1_000_000;
        $maximo = (int) config('centro_operaciones.ticket_imagenes.maximo_megapixeles', 40);
        if ($megapixeles > $maximo) {
            throw ValidationException::withMessages([
                'imagenes' => 'Una imagen supera la resolución máxima de '.$maximo.' megapíxeles.',
            ]);
        }

        $imagen = Image::read($archivo->getPathname())->orient()->scaleDown(
            width: (int) config('centro_operaciones.ticket_imagenes.ancho_maximo', 1600),
            height: (int) config('centro_operaciones.ticket_imagenes.alto_maximo', 1200)
        );
        $contenido = (string) $imagen->toJpeg(
            (int) config('centro_operaciones.ticket_imagenes.calidad', 80)
        );
        unset($imagen);

        $path = 'centro-operaciones/tickets/'.$ticket->id.'/'.Str::uuid().'.jpg';
        if (! Storage::disk('local')->put($path, $contenido)) {
            throw new RuntimeException('No fue posible guardar una de las imágenes.');
        }

        return ['path' => $path, 'size_bytes' => strlen($contenido)];
    }
}
