<?php

namespace App\Exceptions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Session\TokenMismatchException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\View\ViewException;
use App\Mail\BaseMailable;
use App\Support\NotificationAudit;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use PDOException;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use League\Flysystem\UnableToWriteFile;
use League\Flysystem\UnableToReadFile;
use League\Flysystem\FilesystemException as FlysystemException;
use Illuminate\Filesystem\FilesystemException as LaravelFilesystemException;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\QueryException;

class Handler extends ExceptionHandler
{
    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        Event::listen(JobFailed::class, function (JobFailed $event): void {
            $this->alertFailedJob($event);
        });
    }

    /**
     * Render an exception into an HTTP response.
     */
    public function render($request, Throwable $e): Response|\Illuminate\Http\RedirectResponse
    {
        $this->alertCriticalException($request, $e);

        // JSON/API: deja el comportamiento por defecto
        if ($request->expectsJson()) {
            return parent::render($request, $e);
        }

        // Mapeo a vistas personalizadas
        if ($e instanceof TokenMismatchException) {
            return response()->view('errors.419', [], 419);
        }

        if ($e instanceof AuthenticationException) {
            // Por defecto Laravel redirige a login en web; mantenemos ese comportamiento:
            return redirect()->guest(route('login'));
        }

        if ($e instanceof AuthorizationException) {
            return response()->view('errors.403', [], 403);
        }

        if ($e instanceof ModelNotFoundException) {
            return response()->view('errors.404', [], 404);
        }

        if ($e instanceof NotFoundHttpException) {
            return response()->view('errors.404', [], 404);
        }

        if ($e instanceof ThrottleRequestsException) {
            return response()->view('errors.429', [], 429);
        }

        // HttpException con código específico
        if ($e instanceof HttpExceptionInterface) {
            $status = $e->getStatusCode();
            $view = match ($status) {
                401 => 'errors.401',
                403 => 'errors.403',
                404 => 'errors.404',
                419 => 'errors.419',
                429 => 'errors.429',
                503 => 'errors.503',
                default => 'errors.500',
            };
            return response()->view($view, [], $status);
        }

        // Cualquier otro error -> 500 con ID de incidente (y registro en log)
        $exceptionId = (string) Str::uuid();
        report($e); // ya registra con stack/trace

        return response()->view('errors.500', compact('exceptionId'), 500);
    }
    /**
     * Envía alertas por correo para errores críticos que requieren revisión operativa.
     */
    protected function alertCriticalException(Request $request, Throwable $e): void
    {
        $classification = $this->criticalExceptionClassification($e);

        if ($classification === null) {
            return;
        }

        try {
            $root = $this->rootCause($e);
            $fingerprint = sha1(implode('|', [
                $classification,
                get_class($root),
                $root->getMessage(),
                $request->method(),
                $request->path(),
            ]));

            // Evita una tormenta de correos por el mismo error repetido.
            if (! Cache::add('critical_exception_alert:' . $fingerprint, true, now()->addMinutes(10))) {
                return;
            }

            $user = $request->user();
            $route = $request->route();
            $trace = collect($root->getTrace())
                ->take(8)
                ->map(function (array $frame, int $index): string {
                    $file = $frame['file'] ?? '[internal]';
                    $line = $frame['line'] ?? '-';
                    $function = ($frame['class'] ?? '') . ($frame['type'] ?? '') . ($frame['function'] ?? '');

                    return '#' . $index . ' ' . $file . ':' . $line . ' ' . $function;
                })
                ->implode("\n");

            $subject = '[' . config('app.name', 'SLEP AC Postulaciones') . '] Alerta de error crítico: ' . $classification;
            $lines = [
                'Clasificación: ' . $classification,
                'Ambiente: ' . app()->environment(),
                'Fecha/hora: ' . now()->format('Y-m-d H:i:s'),
                'URL: ' . $request->fullUrl(),
                'Método: ' . $request->method(),
                'Ruta: ' . ($route?->getName() ?: 'sin nombre'),
                'Acción: ' . ($route?->getActionName() ?: 'sin acción'),
                'IP: ' . $request->ip(),
                'Usuario: ' . ($user ? (($user->name ?? 'Sin nombre') . ' | ID: ' . $user->id . ' | Email: ' . ($user->email ?? 'sin email')) : 'No autenticado'),
                'Excepción: ' . get_class($root),
                'Mensaje: ' . $root->getMessage(),
                'Archivo: ' . $root->getFile() . ':' . $root->getLine(),
                'Stack reducido:',
                $trace ?: 'Sin stack disponible.',
            ];

            $this->sendCriticalAlertMail(
                subject: $subject,
                greeting: 'Alerta automática de error crítico',
                lines: $lines,
                outroLines: [
                    'Esta alerta fue generada automáticamente por el manejador global de excepciones.',
                    'Se aplicó throttling de 10 minutos para evitar correos repetidos por el mismo error.',
                ],
                meta: [
                    'event_key' => 'critical_exception',
                    'description' => $classification,
                    'subject' => $subject,
                    'triggered_by_user_id' => $user?->id,
                    'context' => [
                        'classification' => $classification,
                        'environment' => app()->environment(),
                        'exception_class' => get_class($root),
                        'message' => $root->getMessage(),
                        'file' => $root->getFile(),
                        'line' => $root->getLine(),
                        'method' => $request->method(),
                        'path' => $request->path(),
                        'route_name' => $route?->getName(),
                        'route_action' => $route?->getActionName(),
                        'fingerprint' => $fingerprint,
                    ],
                ]
            );
        } catch (Throwable $mailException) {
            Log::warning('No se pudo enviar alerta de error crítico por correo.', [
                'original_exception' => get_class($e),
                'mail_exception' => get_class($mailException),
                'mail_message' => $mailException->getMessage(),
            ]);
        }
    }

    /**
     * Envía alertas por correo para jobs fallidos de Laravel Queue.
     */
    protected function alertFailedJob(JobFailed $event): void
    {
        try {
            $root = $this->rootCause($event->exception);
            $classification = $this->criticalExceptionClassification($root) ?: 'Error de job/cola';
            $connection = $event->connectionName ?: 'sin conexión';
            $jobName = method_exists($event->job, 'resolveName') ? $event->job->resolveName() : get_class($event->job);
            $queue = method_exists($event->job, 'getQueue') ? ($event->job->getQueue() ?: 'default') : 'default';

            $fingerprint = sha1(implode('|', [
                'job_failed',
                $classification,
                get_class($root),
                $root->getMessage(),
                $connection,
                $queue,
                $jobName,
            ]));

            if (! Cache::add('critical_exception_alert:' . $fingerprint, true, now()->addMinutes(10))) {
                return;
            }

            $trace = collect($root->getTrace())
                ->take(8)
                ->map(function (array $frame, int $index): string {
                    $file = $frame['file'] ?? '[internal]';
                    $line = $frame['line'] ?? '-';
                    $function = ($frame['class'] ?? '') . ($frame['type'] ?? '') . ($frame['function'] ?? '');

                    return '#' . $index . ' ' . $file . ':' . $line . ' ' . $function;
                })
                ->implode("\n");

            $subject = '[' . config('app.name', 'SLEP AC Postulaciones') . '] Alerta de job fallido: ' . $classification;
            $lines = [
                'Clasificación: ' . $classification,
                'Ambiente: ' . app()->environment(),
                'Fecha/hora: ' . now()->format('Y-m-d H:i:s'),
                'Conexión: ' . $connection,
                'Cola: ' . $queue,
                'Job: ' . $jobName,
                'Excepción: ' . get_class($root),
                'Mensaje: ' . $root->getMessage(),
                'Archivo: ' . $root->getFile() . ':' . $root->getLine(),
                'Stack reducido:',
                $trace ?: 'Sin stack disponible.',
            ];

            $this->sendCriticalAlertMail(
                subject: $subject,
                greeting: 'Alerta automática de job fallido',
                lines: $lines,
                outroLines: [
                    'Esta alerta fue generada automáticamente por el listener de Laravel Queue.',
                    'Se aplicó throttling de 10 minutos para evitar correos repetidos por el mismo job/error.',
                ],
                meta: [
                    'event_key' => 'failed_job',
                    'description' => $classification,
                    'subject' => $subject,
                    'context' => [
                        'classification' => $classification,
                        'environment' => app()->environment(),
                        'exception_class' => get_class($root),
                        'message' => $root->getMessage(),
                        'file' => $root->getFile(),
                        'line' => $root->getLine(),
                        'connection' => $connection,
                        'queue' => $queue,
                        'job' => $jobName,
                        'fingerprint' => $fingerprint,
                    ],
                ]
            );
        } catch (Throwable $mailException) {
            Log::warning('No se pudo enviar alerta de job fallido por correo.', [
                'job_exception' => get_class($event->exception),
                'mail_exception' => get_class($mailException),
                'mail_message' => $mailException->getMessage(),
            ]);
        }
    }

    protected function sendCriticalAlertMail(string $subject, string $greeting, array $lines, array $outroLines, array $meta = []): void
    {
        foreach ($this->criticalAlertRecipients() as $recipient) {
            try {
                NotificationAudit::sendMail($recipient, new BaseMailable(
                    subjectText: $subject,
                    greeting: $greeting,
                    lines: $lines,
                    outroLines: $outroLines
                ), $meta);
            } catch (Throwable $mailException) {
                Log::warning('No se pudo enviar alerta crítica por correo a destinatario.', [
                    'recipient' => $recipient,
                    'subject' => $subject,
                    'mail_exception' => get_class($mailException),
                    'mail_message' => $mailException->getMessage(),
                ]);
            }
        }
    }

    protected function criticalAlertRecipients(): array
    {
        $configured = config('services.internal_alerts.recipients', []);

        if (is_string($configured)) {
            $configured = explode(',', $configured);
        }

        $recipients = collect($configured)
            ->map(fn ($email) => strtolower(trim((string) $email)))
            ->filter(fn ($email) => $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL))
            ->unique()
            ->values()
            ->all();

        return $recipients ?: [
            'alonso.larenas@slepandaliencosta.gob.cl',
            'abel.munoz@slepandaliencosta.gob.cl',
        ];
    }

    protected function criticalExceptionClassification(Throwable $e): ?string
    {
        $root = $this->rootCause($e);
        $rootClass = get_class($root);
        $message = $root->getMessage();
        $file = $root->getFile();

        if ($e instanceof ValidationException || $root instanceof ValidationException) {
            return null;
        }

        if ($e instanceof AuthenticationException || $root instanceof AuthenticationException) {
            return null;
        }

        if ($e instanceof TokenMismatchException || $root instanceof TokenMismatchException) {
            return null;
        }

        if ($e instanceof ThrottleRequestsException || $root instanceof ThrottleRequestsException) {
            return null;
        }

        if ($e instanceof ModelNotFoundException || $root instanceof ModelNotFoundException) {
            return null;
        }

        if ($e instanceof ViewException || str_contains($e->getMessage(), '(View:')) {
            return 'Error de vista';
        }

        if ($root instanceof RouteNotFoundException || $e instanceof RouteNotFoundException || str_contains($message, 'Route [') || str_contains($e->getMessage(), 'Route [')) {
            return 'Error de ruta';
        }

        if ($e instanceof AuthorizationException || $root instanceof AuthorizationException) {
            return 'Error de permisos';
        }

        if ($e instanceof HttpExceptionInterface && $e->getStatusCode() === 403) {
            return 'Error de permisos';
        }

        if ($root instanceof HttpExceptionInterface && $root->getStatusCode() === 403) {
            return 'Error de permisos';
        }

        if ($root instanceof QueryException || $root instanceof PDOException) {
            return 'Error de base de datos';
        }

        if ($root instanceof LaravelFilesystemException || $root instanceof FlysystemException || $root instanceof UnableToReadFile || $root instanceof UnableToWriteFile || $root instanceof FileException) {
            return 'Error de archivos/storage';
        }

        if ($root instanceof TransportExceptionInterface) {
            return 'Error de correo/SMTP';
        }

        if (str_contains($rootClass, 'PhpSpreadsheet') || str_contains($file, 'PhpSpreadsheet') || str_contains($file, 'MaeImportService') || str_contains($file, 'Endeudamiento')) {
            return 'Error de importación Excel/MAE';
        }

        if ($e instanceof NotFoundHttpException || $root instanceof NotFoundHttpException) {
            return 'Ruta no encontrada';
        }

        if ($e instanceof HttpExceptionInterface || $root instanceof HttpExceptionInterface) {
            $status = $e instanceof HttpExceptionInterface ? $e->getStatusCode() : $root->getStatusCode();

            if ($status >= 500) {
                return 'Error HTTP ' . $status;
            }

            return null;
        }

        return 'Error 500 general';
    }

    protected function rootCause(Throwable $e): Throwable
    {
        $root = $e;

        while ($root->getPrevious() instanceof Throwable) {
            $root = $root->getPrevious();
        }

        return $root;
    }

}
