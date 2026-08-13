<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Route;
use App\Support\ChangeLog;
use App\Models\Conversation;
use App\Policies\ConversationPolicy;
use App\Services\CentroOperaciones\IncidenciaCatalogo;
use App\Http\Controllers\Admin\BulkRoleMailController;
use Vonage\Client;
use Vonage\Client\Credentials\Basic;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        require_once app_path('Support/helpers.php');

        $this->app->singleton(IncidenciaCatalogo::class);

        // No bindear manualmente 'request' aquí.
        $this->app->singleton(Client::class, function () {
            $key    = config('services.vonage.key');
            $secret = config('services.vonage.secret');

            return new Client(new Basic($key, $secret));
        });
    }

    public function boot(): void
    {
        Route::middleware(['web', 'auth', 'verified', 'ensure.module', 'ensure.role:admin'])
            ->prefix('admin/correos-por-rol')
            ->name('admin.bulk-role-mail.')
            ->group(function (): void {
                Route::get('/', [BulkRoleMailController::class, 'index'])->name('index');
                Route::post('/', [BulkRoleMailController::class, 'send'])->name('send');
            });

        // Evitar lógica de URL en consola para no interferir con composer/artisan.
        if ($this->app->runningInConsole()) {
            // Si quieres que la paginación Bootstrap afecte también a comandos que renderizan vistas,
            // puedes quitar este return y mover sólo lo delicado (forceScheme) a una condición aparte.
            return;
        }

        // Localización
        app()->setLocale('es');
        app()->setFallbackLocale('es');

        // Paginación con Bootstrap 5
        Paginator::useBootstrap();

        View::composer('*', function ($view) {
            if (!auth()->check()) {
                return;
            }

            $user = auth()->user();
            $allEntries = ChangeLog::visibleEntriesForUser($user);
            $currentEntries = ChangeLog::currentVersionEntriesForUser($user);
            $previousEntries = ChangeLog::previousVersionEntriesForUser($user);
            $currentVersion = ChangeLog::currentVersion();
            $seenVersion = session('changelog_seen_version');
            $shouldShow = !empty($currentEntries)
                && (session('show_changelog_modal', false) || $seenVersion !== $currentVersion);

            $view->with('allChangeLogEntries', $allEntries);
            $view->with('currentChangeLogEntries', $currentEntries);
            $view->with('previousChangeLogEntries', $previousEntries);
            $view->with('currentAppVersion', $currentVersion);
            $view->with('shouldShowChangeLogModal', $shouldShow);
            $view->with('hasVisibleChangeLogEntries', !empty($allEntries));
            $view->with('hasPreviousChangeLogEntries', !empty($previousEntries));
        });

        // Forzar https sólo en producción (opcional)
        // if (config('app.env') === 'production') {
        //     URL::forceScheme('https');
        // }
    }
    protected $policies = [
        \App\Models\UserDocument::class => \App\Policies\UserDocumentPolicy::class,
        Conversation::class => ConversationPolicy::class,
    ];
}
