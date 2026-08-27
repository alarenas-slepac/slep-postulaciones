<?php

namespace App\Policies;

use App\Models\GrupoVotacion;
use App\Models\User;

class GrupoVotacionPolicy
{
    public function operate(User $user, GrupoVotacion $grupo): bool
    {
        if (! $user->can('votaciones.operate-group')) {
            return false;
        }
        if ($user->can('votaciones.admin')) {
            return true;
        }

        return (int) $grupo->encargado_id === (int) $user->id
            || $grupo->miembros()->whereKey($user->id)->exists();
    }

    public function reportIncident(User $user, GrupoVotacion $grupo): bool
    {
        return $user->can('votaciones.report-incidents') && $this->operate($user, $grupo);
    }
}
