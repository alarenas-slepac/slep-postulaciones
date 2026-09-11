<?php

namespace Tests\Feature;

use App\Models\User;
use App\Providers\AppServiceProvider;
use App\Support\ChangeLogViewData;
use Tests\TestCase;

class ChangeLogLazyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);
        $entries = [];
        foreach (range(1, 25) as $n) {
            $entries[] = ['version' => (string) $n, 'title' => 'Entrada sintética '.$n,
                'summary' => 'Contenido sintético', 'items' => ['Sin datos reales'], 'roles' => ['admin'],
                'published_at' => sprintf('2026-08-%02d', $n)];
        }
        $entries[] = ['version' => 'actual', 'title' => 'Actual restringida', 'roles' => ['admin']];
        $entries[] = ['version' => 'publica', 'title' => '<script>general</script>', 'roles' => [], 'items' => ['<img onerror="alert(1)">']];
        config(['changelog.current_version' => 'actual', 'changelog.entries' => $entries]);
    }

    private function user(bool $admin = true): User
    {
        $user = \Mockery::mock(User::class)->makePartial();
        $user->forceFill(['id' => $admin ? 1 : 2]);
        $user->shouldReceive('hasRole')->with('admin')->andReturn($admin);
        $user->shouldReceive('getRoleNames')->andReturn(collect($admin ? ['admin'] : ['funcionario_slep']));
        $user->shouldReceive('saveQuietly')->andReturnTrue();
        return $user;
    }

    public function test_endpoint_requires_authentication_and_filters_roles_before_pagination(): void
    {
        $this->getJson(route('changelog.entries'))->assertUnauthorized();
        $this->actingAs($this->user(false));
        $this->get(route('changelog.entries', ['scope' => 'history']))->assertOk()
            ->assertHeader('X-ChangeLog-Entries', '1')->assertHeader('Cache-Control', 'no-store, private')
            ->assertSee('1 registros.')->assertDontSee('Entrada sintética')->assertDontSee('<script>general</script>', false)
            ->assertSee('&lt;script&gt;general&lt;/script&gt;', false);
        $this->get(route('changelog.entries'))->assertOk()->assertSee('No hay cambios visibles');
    }

    public function test_admin_pages_are_bounded_and_current_entries_are_separate(): void
    {
        $this->actingAs($this->user());
        $first = $this->get(route('changelog.entries', ['scope' => 'history']))->assertOk()->assertDontSee('Actual restringida');
        $this->assertSame(10, substr_count($first->getContent(), '<article '));
        $last = $this->get(route('changelog.entries', ['scope' => 'history', 'page' => 999]))->assertOk()->assertSee('Página 3 de 3');
        $this->assertSame(6, substr_count($last->getContent(), '<article '));
        $current = $this->get(route('changelog.entries'))->assertOk()->assertSee('Actual restringida')->assertDontSee('Entrada sintética');
        $this->assertSame(1, substr_count($current->getContent(), '<article '));
        $this->getJson(route('changelog.entries', ['page' => 0]))->assertUnprocessable();
        $this->getJson(route('changelog.entries', ['scope' => 'all']))->assertUnprocessable();
        $this->get(route('changelog.entries', ['scope' => '', 'page' => '']))->assertOk()->assertSee('Actual restringida');
    }

    public function test_modal_is_only_a_shell_and_acknowledgement_is_preserved(): void
    {
        $this->actingAs($this->user());
        $this->app->getProvider(AppServiceProvider::class)->registerChangeLogViews();
        $html = view('partials.changelog-modal')->render();
        $this->assertStringContainsString('data-auto-show="1"', $html);
        $this->assertStringNotContainsString('Entrada sintética', $html);
        $this->assertStringNotContainsString('Actual restringida', $html);
        $this->assertLessThan(14000, strlen($html));
        session()->put('changelog_seen_version', 'actual');
        $this->assertStringContainsString('data-auto-show="0"', view('partials.changelog-modal')->render());
        $this->postJson(route('changelog.ack'))->assertOk()->assertSessionHas('changelog_seen_version', 'actual');
        $this->assertArrayNotHasKey('allChangeLogEntries', app(ChangeLogViewData::class)->forUser(auth()->user()));
    }

    public function test_request_summary_is_reused_and_is_not_shared_with_another_user(): void
    {
        $admin = \Mockery::mock(User::class)->makePartial();
        $admin->shouldReceive('hasRole')->once()->with('admin')->andReturnTrue();
        $summary = app(ChangeLogViewData::class);
        $this->assertTrue($summary->forUser($admin)['hasCurrentChangeLogEntries']);
        $this->assertTrue($summary->forUser($admin)['hasCurrentChangeLogEntries']);
        $this->assertFalse($summary->forUser($this->user(false))['hasCurrentChangeLogEntries']);
        $this->assertFalse($summary->forUser(null)['hasVisibleChangeLogEntries']);
    }
}
