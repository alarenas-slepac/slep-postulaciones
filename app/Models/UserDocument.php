<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class UserDocument extends Model
{
    public const ARCHIVED_SLOT_PREFIX = 'archived-';

    protected $fillable = [
        'user_id',
        'document_type_id',
        'path',
        'original_name',
        'mime',
        'size',
        'status',
        'reviewer_comment',
        'reviewed_by',
        'reviewed_at',
        'library_slot',
        'credential_label',
        'issued_on',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
        'issued_on' => 'date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function type()
    {
        return $this->belongsTo(DocumentType::class, 'document_type_id');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function scopeInLibrary(Builder $query): Builder
    {
        if (! $query->getModel()->getConnection()->getSchemaBuilder()->hasColumn('user_documents', 'library_slot')) {
            return $query;
        }

        return $query->where('user_documents.library_slot', 'not like', self::ARCHIVED_SLOT_PREFIX.'%');
    }

    public function submissions(): BelongsToMany
    {
        return $this->belongsToMany(BolsaTrabajoPostulacion::class, 'bolsa_trabajo_documentos_presentados', 'user_document_id', 'postulacion_id');
    }
}
