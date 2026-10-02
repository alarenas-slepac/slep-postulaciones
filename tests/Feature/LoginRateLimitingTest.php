<?php

namespace Tests\Feature;

use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Tests\Support\IsolatedSecurityTestCase;

class LoginRateLimitingTest extends IsolatedSecurityTestCase
{
    private function attempt(string $login = 'cuenta1@example.test', string $password = 'incorrecta', string $ip = '192.0.2.10', array $extra = [])
    {
        return $this->withServerVariables(['REMOTE_ADDR' => $ip])->from('/login')
            ->post('/login', ['login' => $login, 'password' => $password] + $extra);
    }

    private function keys(string $login = 'cuenta1@example.test', string $ip = '192.0.2.10'): LoginRequest
    {
        return LoginRequest::create('/login', 'POST', ['login' => $login], [], [], ['REMOTE_ADDR' => $ip]);
    }

    public function test_sixth_attempt_is_blocked_before_authentication_and_unlocks_without_real_wait(): void
    {
        $this->freezeTime();
        $this->testUser();
        foreach (range(1, 5) as $i) {
            $this->attempt()->assertSessionHasErrors(['login' => 'Credenciales inválidas.']);
        }
        $this->attempt(password: 'clave-de-prueba')->assertSessionHasErrors(['login' => 'Demasiados intentos de inicio de sesión. Intenta nuevamente en 60 segundos.']);
        $this->assertGuest();
        $this->assertSame(5, RateLimiter::attempts($this->keys()->throttleKey()));
        $this->travel(59)->seconds();
        $this->attempt()->assertSessionHasErrors(['login' => 'Demasiados intentos de inicio de sesión. Intenta nuevamente en 1 segundo.']);
        $this->travel(2)->seconds();
        $this->attempt(password: 'clave-de-prueba')->assertRedirect(route('dashboard'));
        $this->assertAuthenticated();
    }

    public function test_nonexistent_accounts_are_counted_with_the_same_generic_error(): void
    {
        foreach (range(1, 5) as $i) { $this->attempt('ausente@example.test')->assertSessionHasErrors(['login' => 'Credenciales inválidas.']); }
        $this->assertSame(5, RateLimiter::attempts($this->keys('ausente@example.test')->throttleKey()));
        $this->attempt('ausente@example.test')->assertSessionHasErrors('login');
        $this->assertSame(5, RateLimiter::attempts($this->keys('ausente@example.test')->ipThrottleKey()));
    }

    public function test_rut_and_email_representations_share_hashed_keys_and_counters(): void
    {
        $user = $this->testUser();
        $ruts = ['99.000.001-k', '99000001K', '99 000 001 k', '99.000.001-K', '99000001k'];
        foreach ($ruts as $rut) { $this->attempt($rut)->assertSessionHasErrors(['login' => 'Credenciales inválidas.']); }
        $this->assertSame($this->keys($ruts[0])->throttleKey(), $this->keys($ruts[1])->throttleKey());
        $this->attempt($user->rut, 'clave-de-prueba')->assertSessionHasErrors('login');
        foreach (range(1, 5) as $i) { $this->attempt($i % 2 ? ' CUENTA1@EXAMPLE.TEST ' : 'cuenta1@example.test'); }
        $this->attempt()->assertSessionHasErrors('login');
        $key = $this->keys()->throttleKey();
        $this->assertSame($key, $this->keys(' CUENTA1@EXAMPLE.TEST ')->throttleKey());
        $this->assertMatchesRegularExpression('/^login:identifier:[a-f0-9]{64}$/', $key);
        $this->assertStringNotContainsString($user->email, $key);
        $this->assertStringNotContainsString($user->rut, $this->keys($user->rut)->throttleKey());
    }

    public function test_identifier_and_ip_combinations_are_independent(): void
    {
        foreach (range(1, 5) as $i) { $this->attempt(); }
        $this->attempt('otra@example.test')->assertSessionHasErrors(['login' => 'Credenciales inválidas.']);
        $this->attempt(ip: '192.0.2.11')->assertSessionHasErrors(['login' => 'Credenciales inválidas.']);
        $this->assertSame(1, RateLimiter::attempts($this->keys('otra@example.test')->throttleKey()));
        $this->assertSame(1, RateLimiter::attempts($this->keys(ip: '192.0.2.11')->throttleKey()));
    }

    public function test_ip_limit_blocks_rotating_identifiers_including_valid_credentials(): void
    {
        $this->freezeTime(); $user = $this->testUser();
        foreach (range(1, 30) as $i) { $this->attempt('ausente'.$i.'@example.test')->assertSessionHasErrors(['login' => 'Credenciales inválidas.']); }
        $this->attempt($user->email, 'clave-de-prueba')->assertSessionHasErrors(['login' => 'Demasiados intentos de inicio de sesión. Intenta nuevamente en 60 segundos.']);
        $this->assertGuest();
        $this->assertSame(30, RateLimiter::attempts($this->keys()->ipThrottleKey()));
        $this->travel(61)->seconds();
        $this->attempt($user->email, 'clave-de-prueba')->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_valid_login_clears_only_specific_counter_and_preserves_remember_and_session(): void
    {
        $user = $this->testUser();
        $this->attempt(); $this->attempt(); $this->attempt('otra@example.test');
        $this->withSession(['changelog_seen_version' => 'anterior']);
        $oldSessionId = session()->getId();
        $this->attempt(password: 'clave-de-prueba', extra: ['remember' => 'on'])->assertRedirect(route('dashboard'))
            ->assertSessionHas('show_changelog_modal', true)->assertSessionMissing('changelog_seen_version');
        $this->assertAuthenticatedAs($user);
        $this->assertNotSame($oldSessionId, session()->getId());
        $this->assertNotEmpty($user->fresh()->remember_token);
        $this->assertSame(0, RateLimiter::attempts($this->keys()->throttleKey()));
        $this->assertSame(3, RateLimiter::attempts($this->keys()->ipThrottleKey()));
    }

    public function test_requested_role_and_intended_redirections_are_preserved(): void
    {
        $this->testUser(role: 2);
        $this->attempt(password: 'clave-de-prueba', extra: ['active_role' => ' FUNCIONARIO_AC '])
            ->assertRedirect(route('tramites.cargas-familiares.index'))->assertSessionHas('active_role', 'funcionario_ac');
        Auth::logout();
        $this->withSession(['url.intended' => '/destino-protegido']);
        $this->attempt(password: 'clave-de-prueba', extra: ['active_role' => 'funcionario_ac'])->assertRedirect('/destino-protegido');
        Auth::logout();
        $this->attempt(password: 'clave-de-prueba', extra: ['active_role' => 'admin'])
            ->assertSessionHasErrors(['login' => 'La cuenta no tiene habilitado el rol solicitado.']);
        $this->assertGuest();
    }

    public function test_valid_formatted_rut_login_clears_its_normalized_counter(): void
    {
        $user = $this->testUser();
        $this->attempt('99000001k')->assertSessionHasErrors(['login' => 'Credenciales inválidas.']);
        $this->attempt(' 99.000.001-k ', 'clave-de-prueba')->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
        $this->assertSame(0, RateLimiter::attempts($this->keys($user->rut)->throttleKey()));
        $this->assertSame(1, RateLimiter::attempts($this->keys()->ipThrottleKey()));
    }

    public function test_limits_are_configurable_and_identifier_length_is_validated(): void
    {
        config(['auth.login_limits.identifier_ip' => 2, 'auth.login_limits.ip' => 4, 'auth.login_limits.decay_seconds' => 10]);
        $this->freezeTime();
        $this->attempt(); $this->attempt();
        $this->attempt()->assertSessionHasErrors(['login' => 'Demasiados intentos de inicio de sesión. Intenta nuevamente en 10 segundos.']);
        $this->travel(11)->seconds();
        $this->attempt()->assertSessionHasErrors(['login' => 'Credenciales inválidas.']);
        $this->attempt(str_repeat('a', 256))->assertSessionHasErrors('login');
    }
}
