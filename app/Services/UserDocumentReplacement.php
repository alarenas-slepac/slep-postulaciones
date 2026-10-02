<?php

namespace App\Services;

use App\Models\DocumentType;
use App\Models\User;
use App\Models\UserDocument;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class UserDocumentReplacement
{
    public function lockKey(int $userId, int $typeId): string
    {
        return "user-document-upload:{$userId}:{$typeId}";
    }

    public function replace(User $user, DocumentType $type, UploadedFile $file, string $filename): UserDocument
    {
        $result = Cache::lock($this->lockKey($user->id, $type->id), 120)->get(function () use ($user, $type, $file, $filename) {
            $connection = $user->getConnection();
            // Esta acción HTTP no tiene una transacción exterior. No se borra
            // un archivo mientras sus metadatos puedan revertirse más adelante.
            if ($connection->transactionLevel() !== 0) {
                throw new \LogicException('El reemplazo documental requiere una transacción propia.');
            }

            $disk = Storage::disk('public');
            // UUID en el directorio: basename sigue siendo descriptivo en las
            // descargas y ZIP existentes. Los IDs acotan la longitud del path.
            $directory = "documents/{$user->id}/{$type->id}/".Str::uuid();
            $newPath = $directory.'/'.$filename;
            $oldPath = null;

            try {
                $storedPath = $disk->putFileAs($directory, $file, $filename);
                if ($storedPath !== $newPath || ! $disk->exists($newPath)) {
                    throw new \RuntimeException('No se confirmó la escritura del documento.');
                }

                $document = $connection->transaction(function () use ($user, $type, $file, $newPath, &$oldPath, $connection) {
                    // Bloquea también la primera carga, cuando aún no hay fila
                    // en user_documents. La clave única histórica es otra defensa.
                    User::on($connection->getName())->whereKey($user->id)->lockForUpdate()->firstOrFail();
                    $document = UserDocument::on($connection->getName())
                        ->where('user_id', $user->id)->where('document_type_id', $type->id)
                        ->lockForUpdate()->first();
                    $document ??= (new UserDocument)->setConnection($connection->getName());
                    $oldPath = $document->path;
                    $document->fill([
                        'user_id' => $user->id,
                        'document_type_id' => $type->id,
                        'path' => $newPath,
                        'original_name' => $file->getClientOriginalName(),
                        'mime' => 'application/pdf',
                        'size' => $file->getSize(),
                        'status' => 'pending',
                        'reviewer_comment' => null,
                        'reviewed_by' => null,
                        'reviewed_at' => null,
                    ]);
                    if (! $document->save()) {
                        throw new \RuntimeException('No se confirmó la actualización del documento.');
                    }

                    return $document;
                });
            } catch (Throwable $exception) {
                $this->cleanFile($newPath, $user->id, $type->id, 'unassociated', false);
                // No incluye nombres, contraseñas, tokens ni el SQL de la excepción.
                Log::warning('Falló el reemplazo documental; se conservó el anterior.', [
                    'user_id' => $user->id, 'document_type_id' => $type->id,
                    'failure_type' => get_class($exception),
                ]);
                throw ValidationException::withMessages([
                    'file' => 'No fue posible guardar el documento. Se conservó el anterior; intenta nuevamente.',
                ]);
            }

            // La transacción ya se confirmó. Un problema de limpieza no invalida
            // el documento nuevo ni convierte esta carga en un error de guardado.
            if ($oldPath && $oldPath !== $newPath) {
                $this->cleanFile($oldPath, $user->id, $type->id, 'superseded', true);
            }

            return $document;
        });

        if ($result === false) {
            throw ValidationException::withMessages([
                'file' => 'Hay otra carga en curso para este documento. Intenta nuevamente al finalizar.',
            ]);
        }

        return $result;
    }

    private function cleanFile(string $path, int $userId, int $typeId, string $phase, bool $checkReferences): void
    {
        try {
            // Algunas rutas históricas pueden estar compartidas. No se elimina
            // un archivo que aún figure asociado a cualquier otro documento.
            if ($checkReferences && UserDocument::where('path', $path)->exists()) {
                return;
            }
            $disk = Storage::disk('public');
            if ($disk->exists($path) && ! $disk->delete($path)) {
                throw new \RuntimeException('La eliminación devolvió false.');
            }
        } catch (Throwable $exception) {
            // Hash rastreable al inventariar el disco, sin publicar el nombre
            // personal que pueda contener la ruta histórica.
            Log::warning('Documento pendiente de limpieza de almacenamiento.', [
                'user_id' => $userId, 'document_type_id' => $typeId, 'disk' => 'public',
                'path_sha256' => hash('sha256', $path), 'phase' => $phase,
                'failure_type' => get_class($exception),
            ]);
        }
    }
}
