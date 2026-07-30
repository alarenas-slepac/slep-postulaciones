<?php

namespace App\Jobs;

use App\Models\DeclaracionDocumentosExport;
use App\Models\DeclaracionSostenedor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;
use ZipArchive;

class GenerateDeclaracionDocumentosExport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $exportId;

    public int $timeout = 3600;

    public int $tries = 1;

    public function __construct(int $exportId)
    {
        $this->exportId = $exportId;
    }

    public function handle(): void
    {
        $export = DeclaracionDocumentosExport::find($this->exportId);
        if (!$export) {
            return;
        }

        $export->update([
            'status' => 'processing',
            'started_at' => now(),
            'error_message' => null,
        ]);

        $disk = Storage::disk('local');
        DeclaracionDocumentosExport::purgeObsoleteGeneratedFiles();

        $directory = 'exports/declaracion-documentos';
        $disk->makeDirectory($directory);

        $relativeZipPath = $directory . '/' . $export->file_name;
        $absoluteZipPath = $disk->path($relativeZipPath);

        if (file_exists($absoluteZipPath)) {
            @unlink($absoluteZipPath);
        }

        $zip = new ZipArchive();
        if ($zip->open($absoluteZipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            $export->update([
                'status' => 'error',
                'error_message' => 'No se pudo crear el archivo ZIP de exportación.',
            ]);
            return;
        }

        $filters = is_array($export->filtros_json) ? $export->filtros_json : [];
        $query = DeclaracionSostenedor::query()
            ->select(['id', 'rbd', 'rut', 'certificado_titulo', 'certificado_antecedentes']);

        if (!empty($filters['rut'])) {
            $query->where('rut', 'like', '%' . trim((string) $filters['rut']) . '%');
        }

        if (!empty($filters['nombre'])) {
            $nombre = trim((string) $filters['nombre']);
            $query->where(function ($q) use ($nombre) {
                $q->where('nombres', 'like', '%' . $nombre . '%')
                    ->orWhere('apellido_paterno', 'like', '%' . $nombre . '%')
                    ->orWhere('apellido_materno', 'like', '%' . $nombre . '%');
            });
        }

        if (!empty($filters['establecimiento'])) {
            $query->where('rbd', trim((string) $filters['establecimiento']));
        }

        if (($export->tab ?? 'docentes') === 'docentes') {
            $query->where('estamento', 'DOCENTE');
        } else {
            $query->where('estamento', 'ASISTENTE');
        }

        $recordsCount = (clone $query)->count();
        $filesCount = 0;

        $query->orderBy('id')->chunkById(200, function ($registros) use (&$filesCount, $disk, $zip) {
            foreach ($registros as $registro) {
                foreach (['certificado_titulo', 'certificado_antecedentes'] as $campo) {
                    $relativePath = (string) ($registro->{$campo} ?? '');
                    if ($relativePath === '' || !str_starts_with($relativePath, 'declaracion/')) {
                        continue;
                    }
                    if (!$disk->exists($relativePath)) {
                        continue;
                    }

                    $absolutePath = $disk->path($relativePath);
                    if (!is_file($absolutePath)) {
                        continue;
                    }

                    $zip->addFile($absolutePath, $relativePath);
                    $filesCount++;
                }
            }
        });

        $zip->close();

        if ($filesCount === 0) {
            @unlink($absoluteZipPath);
            $export->update([
                'status' => 'error',
                'records_count' => $recordsCount,
                'files_count' => 0,
                'error_message' => 'No se encontraron documentos cargados para los registros del filtro aplicado.',
            ]);
            return;
        }

        $export->update([
            'status' => 'completed',
            'file_path' => $relativeZipPath,
            'records_count' => $recordsCount,
            'files_count' => $filesCount,
            'completed_at' => now(),
        ]);
    }

    public function failed(Throwable $exception): void
    {
        $export = DeclaracionDocumentosExport::find($this->exportId);
        if (!$export) {
            return;
        }

        if (!empty($export->file_path)) {
            Storage::disk('local')->delete($export->file_path);
        }

        $export->update([
            'status' => 'error',
            'completed_at' => now(),
            'error_message' => mb_substr($exception->getMessage(), 0, 1000),
        ]);

        Log::error('Fallo exportación asíncrona de documentos de declaración.', [
            'export_id' => $this->exportId,
            'message' => $exception->getMessage(),
        ]);
    }
}
