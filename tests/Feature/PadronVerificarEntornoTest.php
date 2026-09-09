<?php

namespace Tests\Feature;

use App\Services\Padron\PadronAplicacionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class PadronVerificarEntornoTest extends TestCase
{
    public function test_sqlite_is_not_reported_as_mysql_certification_and_does_not_query_data(): void
    {
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        $queries = [];
        DB::listen(function ($event) use (&$queries) { $queries[] = $event->sql; });
        $this->assertSame(1, Artisan::call('padron:verificar-entorno', ['--json' => true]));
        $report = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertFalse($report['habilita_aplicacion']);
        $this->assertFalse($report['requisitos_tecnicos_ok']);
        $this->assertSame([], $queries);
        $this->assertFalse(app(PadronAplicacionService::class)->disponible());
    }
}
