<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Route;
use App\Support\ChangeLogViewData;
use App\Http\Controllers\ChangeLogController;
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
        $this->app->scoped(\App\Services\Padron\PadronEscrituraService::class);
        $this->app->scoped(ChangeLogViewData::class);

        // No bindear manualmente 'request' aquí.
        $this->app->singleton(Client::class, function () {
            $key    = config('services.vonage.key');
            $secret = config('services.vonage.secret');

            return new Client(new Basic($key, $secret));
        });
    }

    public function boot(): void
    {
        Route::middleware(['web', 'auth'])->get('/changelog/entries', [ChangeLogController::class, 'entries'])
            ->name('changelog.entries');
        Route::middleware(['web', 'auth', 'verified', 'ensure.role:admin|coordinador_gdp'])
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

        $this->registerChangeLogViews();

        // Forzar https sólo en producción (opcional)
        // if (config('app.env') === 'production') {
        //     URL::forceScheme('https');
        // }
    }

    /** También permite ejercitar en CLI la composición real usada por las peticiones web. */
    public function registerChangeLogViews(): void
    {
        View::composer(['layouts.app', 'partials.footer', 'partials.changelog-modal'], function ($view) {
            $data = app(ChangeLogViewData::class)->forUser(auth()->user());
            $data['shouldShowChangeLogModal'] = $data['hasCurrentChangeLogEntries']
                && (session('show_changelog_modal', false) || session('changelog_seen_version') !== $data['currentAppVersion']);
            $view->with($data);
        });
    }
    protected $policies = [
        \App\Models\UserDocument::class => \App\Policies\UserDocumentPolicy::class,
        Conversation::class => ConversationPolicy::class,
    ];
}
