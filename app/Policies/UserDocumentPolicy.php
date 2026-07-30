<?php

namespace App\Policies;

use App\Models\User;
use App\Models\UserDocument;

class UserDocumentPolicy
{
    public function viewAny(User $user): bool
    {
        // Lectura de documentos (gestión / buscador de postulantes)
        return $user->hasAnyRole(['admin', 'funcionario_slep', 'coordinador_gdp', 'coordinador_uatp']);
    }

    public function view(User $user, UserDocument $doc): bool
    {
        return $user->hasAnyRole(['admin', 'funcionario_slep', 'coordinador_gdp', 'coordinador_uatp']) || $doc->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['postulante', 'funcionario']);
    }

    public function update(User $user, UserDocument $doc): bool
    {
        return $doc->user_id === $user->id; // reemplazar propio archivo
    }

    public function review(User $user, ?UserDocument $doc = null): bool
    {
        return $user->hasAnyRole(['admin', 'funcionario_slep', 'coordinador_gdp']);
    }

    public function delete(User $user, UserDocument $doc): bool
    {
        return $user->hasAnyRole(['admin']) || $doc->user_id === $user->id;
    }
}
