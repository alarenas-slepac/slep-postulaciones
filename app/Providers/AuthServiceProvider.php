<?php

namespace App\Providers;

use App\Models\Conversation;
use App\Models\GrupoVotacion;
use App\Policies\ConversationPolicy;
use App\Policies\GrupoVotacionPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * Mapeo de políticas.
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Conversation::class => ConversationPolicy::class,
        GrupoVotacion::class => GrupoVotacionPolicy::class,
    ];

    /**
     * Registra las políticas.
     */
    public function boot(): void
    {
        // IMPORTANTE: registrar las policies del arreglo $policies
        $this->registerPolicies();

        // Aquí podrías definir Gates si lo necesitas.
        // Gate::define('algo', fn($user) => true);
    }
}
