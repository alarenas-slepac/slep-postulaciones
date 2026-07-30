<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class DeclaracionDocumentosExport extends Model
{
    use HasFactory;

    protected $table = 'declaracion_documentos_exports';

    protected $fillable = [
        'user_id',
        'tab',
        'filtros_json',
        'status',
        'file_path',
        'file_name',
        'records_count',
        'files_count',
        'error_message',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'filtros_json' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];


    public static function purgeObsoleteGeneratedFiles(int $days = 7): void
    {
        $threshold = now()->subDays(max(1, $days));

        static::query()
            ->whereNotNull('file_path')
            ->whereIn('status', ['completed', 'error'])
            ->where('updated_at', '<', $threshold)
            ->orderBy('id')
            ->chunkById(100, function ($exports) {
                foreach ($exports as $export) {
                    if (!empty($export->file_path)) {
                        Storage::disk('local')->delete($export->file_path);
                    }

                    $export->delete();
                }
            });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
