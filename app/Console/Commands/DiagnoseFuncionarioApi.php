<?php

namespace App\Console\Commands;

use App\Models\FuncionarioApiIntegration;
use Illuminate\Console\Command;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\Sanctum;

class DiagnoseFuncionarioApi extends Command
{
    protected $signature = 'funcionarios-api:diagnosticar
        {integration=1 : ID de la integración}
        {--token-file= : Archivo privado con el token completo; nunca pasar el token como argumento}';

    protected $description = 'Diagnóstico de lectura de la integración, vigencia y token, sin revelar credenciales ni consultar personas.';

    public function handle(): int
    {
        if (!ctype_digit((string) $this->argument('integration')) || (int) $this->argument('integration') < 1) {
            $this->line(json_encode(['error' => 'integration_id_invalid']));
            return self::INVALID;
        }
        try {
            $integration = FuncionarioApiIntegration::find((int) $this->argument('integration'));
            if (!$integration) {
                $this->line(json_encode(['integration_exists' => false]));
                return self::FAILURE;
            }
            $provider = config('auth.guards.sanctum.provider');
            $model = $provider ? config('auth.providers.'.$provider.'.model') : null;
            $providerAccepts = $provider === null || (is_string($model) && is_a($integration, $model));
            $tokens = $integration->tokens()->get();
            $report = [
                'integration_id' => $integration->id, 'integration_exists' => true,
                'integration_enabled' => $integration->enabled,
                'tokens_registered' => $tokens->count(),
                'tokens_current_with_ability' => $tokens->filter(fn ($token) => $this->current($token)
                    && $token->can(FuncionarioApiIntegration::ABILITY))->count(),
                'guard_accepts_integration' => $providerAccepts,
                'global_expiration_minutes' => config('sanctum.expiration'),
                'custom_auth_callback_configured' => is_callable(Sanctum::$accessTokenAuthenticationCallback),
                'authorization_rule_in_project' => str_contains((string) @file_get_contents(public_path('.htaccess')), 'E=HTTP_AUTHORIZATION:%{HTTP:Authorization}'),
                'http_header_forwarding' => 'not_verified_from_cli',
            ];
            $ready = $integration->enabled && $providerAccepts && $report['tokens_current_with_ability'] > 0;
            if ($this->option('token-file') !== null) {
                $secret = $this->readPrivateToken((string) $this->option('token-file'));
                if ($secret === null) {
                    $this->line(json_encode(['error' => 'token_file_invalid_or_not_private']));
                    return self::FAILURE;
                }
                $tokenModel = Sanctum::$personalAccessTokenModel;
                $matched = $tokenModel::findToken($secret);
                unset($secret);
                $belongs = $matched && $matched->tokenable_type === $integration->getMorphClass()
                    && (int) $matched->tokenable_id === $integration->id;
                $report += [
                    'token_matches_database' => $matched !== null,
                    'token_belongs_to_integration' => (bool) $belongs,
                    'token_within_expiration' => $matched ? $this->current($matched) : false,
                    'token_has_ability' => $matched ? $matched->can(FuncionarioApiIntegration::ABILITY) : false,
                ];
                $ready = $ready && $belongs && $report['token_within_expiration'] && $report['token_has_ability'];
            }
            $report['credentials_ready'] = (bool) $ready;
            $this->line(json_encode($report, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
            return $ready ? self::SUCCESS : self::FAILURE;
        } catch (\Throwable) {
            // Nunca volcar excepciones, SQL, rutas privadas ni secretos en la salida.
            $this->line(json_encode(['error' => 'database_or_configuration_unavailable']));
            return self::FAILURE;
        }
    }

    private function current(PersonalAccessToken $token): bool
    {
        $minutes = config('sanctum.expiration');
        return $token->expires_at?->isFuture()
            && (!$minutes || ($token->created_at && $token->created_at->gt(now()->subMinutes((int) $minutes))));
    }

    private function readPrivateToken(string $input): ?string
    {
        $path = realpath($input);
        $public = realpath(public_path());
        if (!$path || !$public || !is_file($path) || !is_readable($path)) return null;
        $comparablePath = str_replace('\\', '/', $path);
        if (DIRECTORY_SEPARATOR === '\\') {
            $comparablePath = strtolower($comparablePath);
        }
        $publicRoots = [$public, config('filesystems.disks.public.root'), ...array_values(config('filesystems.links', []))];
        foreach ($publicRoots as $root) {
            $root = is_string($root) ? realpath($root) : false;
            if (!$root) continue;
            $comparablePublic = rtrim(str_replace('\\', '/', $root), '/').'/';
            if (DIRECTORY_SEPARATOR === '\\') $comparablePublic = strtolower($comparablePublic);
            if (str_starts_with($comparablePath, $comparablePublic)) return null;
        }
        if (DIRECTORY_SEPARATOR === '/' && (fileperms($path) & 0077) !== 0) return null;
        $value = @file_get_contents($path, false, null, 0, 4097);
        if ($value === false || strlen($value) > 4096) return null;
        $value = trim($value);
        return preg_match('/^[0-9]+\|[^\s|]+$/D', $value) ? $value : null;
    }
}
