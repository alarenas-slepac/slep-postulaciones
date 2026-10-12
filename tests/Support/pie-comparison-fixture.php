<?php

// Catálogo real exportado sólo desde SQLite en memoria para comprobar el cálculo del navegador.
foreach (['APP_ENV' => 'testing', 'APP_CONFIG_CACHE' => sys_get_temp_dir().'/sga-pie-fixture-'.bin2hex(random_bytes(8)).'.php',
    'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:', 'DB_URL' => 'null', 'CACHE_STORE' => 'array',
    'SESSION_DRIVER' => 'array', 'MAIL_MAILER' => 'array'] as $name => $value) {
    putenv($name.'='.$value); $_ENV[$name] = $_SERVER[$name] = $value;
}
require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if ($app->configurationIsCached() || config('database.default') !== 'sqlite'
    || Illuminate\Support\Facades\DB::connection()->getDatabaseName() !== ':memory:') throw new RuntimeException('Non-isolated fixture environment');
Illuminate\Support\Facades\Http::preventStrayRequests();
Illuminate\Support\Facades\Mail::fake(); Illuminate\Support\Facades\Queue::fake();
(require database_path('migrations/2026_05_25_170000_create_pie_horas_apoyo_minimo_table.php'))->up();
$rules = App\Models\PieHorasApoyoMinimo::where('vigente', true)->get();
$matrix = []; $cases = [];
foreach (['con_jec', 'sin_jec', 'sin_jec:epja'] as $key) {
    $epja = str_contains($key, ':epja');
    $course = new App\Models\EstablecimientoCurso(['nombre_seccion' => $epja ? 'Curso EPJA de prueba' : 'Curso de prueba', 'regimen_jec' => $key === 'sin_jec' ? 'SIN JEC' : 'CON JEC']);
    $course->setRelation('curso', new App\Models\Curso(['nombre' => $epja ? 'Nivel EPJA' : '7° Básico', 'codigo' => $epja ? 'EPJA' : '7B']));
    $course->setRelation('planEstudio', null);
    foreach ([0, 1, 5] as $neet) for ($neep = 0; $neep <= 32; $neep++) {
        $matrix[$key][$neet][$neep] = array_intersect_key(App\Support\PieHorasCalculator::calculate($course, $neet, $neep, $rules),
            array_flip(['total_crono_minutos', 'prof_educ_dif_minutos', 'pae_minutos', 'calculo_observacion']));
    }
    foreach ([[0, 0], [0, 2], [1, 1], [4, 1], [10, 0], [6, 31], [1, 39]] as [$neet, $neep]) {
        $cases[] = ['key' => $key, 'neet' => $neet, 'neep' => $neep,
            'expected' => array_intersect_key(App\Support\PieHorasCalculator::calculate($course, $neet, $neep, $rules),
                array_flip(['total_crono_minutos', 'prof_educ_dif_minutos', 'pae_minutos', 'calculo_observacion']))];
    }
}
echo json_encode(['matrix' => $matrix, 'cases' => $cases], JSON_THROW_ON_ERROR);
