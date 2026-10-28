<?php

namespace Tests\Feature;

use App\Models\AreaDesempeno;
use App\Models\Commune;
use App\Models\Establecimiento;
use App\Models\PostulantProfile;
use App\Models\PostulantProfileContrato;
use App\Models\SolicitudReemplazo;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\IsolatedSecurityTestCase;

class BuscadorPostulantesVistaTest extends IsolatedSecurityTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-10-28 12:00:00', 'America/Santiago'));
        Schema::create('establecimientos', function (Blueprint $t): void {
            $t->id(); $t->unsignedInteger('rbd'); $t->string('nombre_establecimiento'); $t->string('comuna')->nullable();
            $t->timestamps();
        });
        Schema::create('postulant_profiles', function (Blueprint $t): void {
            $t->id(); $t->foreignId('user_id')->constrained('users');
        });
        (require database_path('migrations/2026_04_28_120000_create_postulant_profile_contratos_table.php'))->up();
        $this->withoutVite();
        $this->app['request']->setLaravelSession($this->app['session.store']);
        Storage::disk('local')->put('buscador-ui/layouts/app.blade.php', '<!doctype html><html lang="es"><head><meta name="viewport" content="width=device-width,initial-scale=1">@stack(\'styles\')</head><body>@yield(\'content\')@stack(\'scripts\')</body></html>');
        $this->app['view']->getFinder()->prependLocation(Storage::disk('local')->path('buscador-ui'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_la_consulta_de_vinculos_vigentes_muestra_tarjeta_amarilla_y_todos_sus_detalles(): void
    {
        $user = $this->testUser();
        DB::table('postulant_profiles')->insert(['id' => 10, 'user_id' => $user->id]);
        $ee = Establecimiento::forceCreate(['rbd' => 99001, 'nombre_establecimiento' => 'Escuela sintética de prueba']);
        foreach ([['Plazo Fijo', '2026-10-28', true], ['Contrata', '2026-12-31', true], ['Indefinido', null, true],
            ['Honorarios', '2026-10-27', true], ['Honorarios', '2026-12-31', false]] as [$tipo, $fecha, $activo]) {
            PostulantProfileContrato::create(['postulant_profile_id' => 10, 'tipo_contrato' => $tipo,
                'fecha_termino' => $fecha, 'activo' => $activo, 'cantidad_horas' => 22, 'establecimiento_id' => $ee->id]);
        }
        $perfil = PostulantProfile::with('contratosLaboralesActivos.establecimiento')->findOrFail(10);
        $this->complete($perfil, $user);
        $this->assertCount(3, $perfil->contratosLaboralesActivos);
        $antes = DB::table('postulant_profile_contratos')->get()->toJson();
        $html = $this->render([$perfil]); $xpath = $this->xpath($html);
        $this->assertSame(1, $xpath->query('//tr[@id="perfil-10"]//section[contains(@class,"--warning")]')->length);
        $this->assertSame(3, $xpath->query('//tr[@id="perfil-10"]//section[contains(@class,"--warning")]//li')->length);
        foreach (['Vinculación laboral activa registrada', 'Escuela sintética de prueba', '99001', '22 h', '28-10-2026', '31-12-2026', 'Sin fecha de término registrada'] as $texto) { $this->assertStringContainsString($texto, $html); }
        $this->assertStringNotContainsString('Honorarios', $html);
        $this->assertSame(0, $xpath->query('//tr[@id="perfil-10"]//*[@title]')->length);
        $this->assertSame($antes, DB::table('postulant_profile_contratos')->get()->toJson());
    }

    public function test_vinculo_vencido_o_desactivado_no_se_presenta_como_laboral_activo(): void
    {
        $user = $this->testUser(); DB::table('postulant_profiles')->insert(['id' => 10, 'user_id' => $user->id]);
        foreach ([['2026-10-27', true], ['2026-12-31', false]] as [$fecha, $activo]) {
            PostulantProfileContrato::create(['postulant_profile_id' => 10, 'tipo_contrato' => 'Contrata', 'fecha_termino' => $fecha, 'activo' => $activo]);
        }
        $perfil = PostulantProfile::with('contratosLaboralesActivos')->findOrFail(10); $this->complete($perfil, $user);
        $html = $this->render([$perfil]); $xpath = $this->xpath($html);
        $this->assertSame(0, $xpath->query('//tr[@id="perfil-10"]//section[contains(@class,"--warning")]')->length);
        $this->assertStringContainsString('Sin vinculación laboral activa registrada', $html);
        $this->assertStringContainsString('no confirma disponibilidad', $html);
    }

    public function test_trabajo_externo_y_reemplazo_no_ocultan_la_tarjeta_amarilla(): void
    {
        $perfil = $this->profile();
        $perfil->user->forceFill(['trabaja_en_otro_lugar' => true, 'trabaja_en_otro_lugar_observacion' => 'Trabajo externo sintético']);
        $perfil->setRelation('contratosLaboralesActivos', collect([$this->contrato(), $this->contrato(99002, 14)]));
        $solicitud = (new SolicitudReemplazo)->forceFill(['id' => 99, 'numero_solicitud' => '2026-0099', 'fecha_termino' => '2026-11-03'])
            ->setRelation('establecimiento', (new Establecimiento)->forceFill(['nombre_establecimiento' => 'Local sintético']));
        $perfil->setRelation('reemplazosActivosActuales', collect([$solicitud]));
        $html = $this->render([$perfil]); $xpath = $this->xpath($html);
        $cards = $xpath->query('//tr[@id="perfil-10"]//section');
        $this->assertSame(3, $cards->length);
        $this->assertStringContainsString('--warning', $cards->item(0)->getAttribute('class'));
        $this->assertStringContainsString('Trabajo externo sintético', $html);
        $this->assertStringContainsString('2026-0099', $html); $this->assertStringContainsString('03-11-2026', $html);
        $this->assertStringContainsString('14 h', $html); $this->assertStringContainsString('99002', $html);
    }

    public static function documentos(): array { return [[true, 'Documentos cargados'], [false, 'Faltan 2 documentos'], [null, 'Sin documentos obligatorios definidos']]; }

    #[DataProvider('documentos')]
    public function test_documentacion_se_muestra_sin_depender_de_tooltips_y_distingue_aprobacion(?bool $completos, string $texto): void
    {
        $perfil = $this->profile(); $perfil->setAttribute('documentos_obligatorios_estado', ['is_complete' => $completos, 'missing' => 2, 'tooltip' => 'Detalle sintético de documentos.']);
        $html = $this->render([$perfil]);
        $this->assertStringContainsString($texto, $html); $this->assertStringContainsString('Detalle sintético de documentos.', $html);
        if ($completos) { $this->assertStringContainsString('La carga no implica aprobación administrativa.', $html); }
    }

    public function test_filtros_conservan_etiquetas_valores_y_mencion_libre_con_selectores_institucionales(): void
    {
        $html = $this->render([], ['filters' => ['area_desempeno_id' => 1, 'mencion' => 'Mención libre sintética', 'nombre' => 'Persona', 'exp_min' => 0, 'per_page' => 25]]);
        $xpath = $this->xpath($html);
        $this->assertSame(1, $xpath->query('//h1')->length);
        $this->assertSame(5, $xpath->query('//select[@data-slep-select]')->length);
        foreach ($xpath->query('//form//input | //form//select') as $input) {
            $this->assertNotSame('', $input->getAttribute('id'));
            $this->assertSame(1, $xpath->query('//label[@for="'.$input->getAttribute('id').'"]')->length);
        }
        $this->assertSame(1, $xpath->query('//select[@name="mencion"]/option[@selected and @value="Mención libre sintética"]')->length);
        $this->assertSame(0, $xpath->query('//*[@data-slep-conditional="docente"][@hidden]')->length);
        $this->assertSame(1, $xpath->query('//input[@name="exp_min" and @value="0"]')->length);
        $this->assertSame(1, $xpath->query('//select[@name="per_page"]/option[@value="25" and @selected]')->length);
        $this->assertSame(0, $xpath->query('//datalist | //input[@type="number"] | //*[@data-bs-toggle="tooltip"]')->length);
    }

    public function test_area_asistente_desactiva_y_oculta_especialidad_y_mencion(): void
    {
        $xpath = $this->xpath($this->render([], ['filters' => ['area_desempeno_id' => 3]]));
        $this->assertSame(2, $xpath->query('//*[@data-slep-conditional and @hidden]')->length);
        $this->assertSame(1, $xpath->query('//select[@name="mencion" and @disabled]')->length);
        $this->assertSame(1, $xpath->query('//select[@name="especialidad_tp" and @disabled]')->length);
    }

    public function test_paginacion_mantiene_filtros_y_muestra_ventana_acotada_sin_enlaces_deshabilitados(): void
    {
        $pagina = new LengthAwarePaginator([$this->profile()], 20000, 15, 37, ['path' => '/reemplazos/buscador-postulantes']);
        $pagina->appends(['nombre' => 'Persona sintética', 'area_desempeno_id' => 1]);
        $html = $this->render([], ['profiles' => $pagina]); $xpath = $this->xpath($html);
        $this->assertSame(1, $xpath->query('//nav[contains(@class,"slep-pagination")]//*[@aria-current="page" and text()="37"]')->length);
        $links = $xpath->query('//nav[contains(@class,"slep-pagination")]//a');
        $this->assertLessThanOrEqual(8, $links->length);
        foreach ($links as $link) {
            parse_str(parse_url($link->getAttribute('href'), PHP_URL_QUERY), $query);
            $this->assertSame('Persona sintética', $query['nombre']); $this->assertSame('1', $query['area_desempeno_id']);
        }
        $this->assertSame(2, $xpath->query('//span[contains(@class,"slep-pagination__gap")]')->length);
    }

    public function test_estado_vacio_y_campos_desconocidos_son_legibles(): void
    {
        $html = $this->render([]);
        $this->assertStringContainsString('No encontramos personas con esos filtros', $html);
        $this->assertStringContainsString('No hay resultados para mostrar.', $html);
        $html = $this->render([$this->profile()]);
        $this->assertStringContainsString('Experiencia no informada', $html);
        $this->assertStringContainsString('Teléfono no informado', $html);
    }

    public function test_escapa_nombres_observaciones_y_textos_en_selectores(): void
    {
        $perfil = $this->profile();
        $perfil->user->forceFill(['nombres' => '<img src=x onerror=alert(1)>', 'trabaja_en_otro_lugar' => true, 'trabaja_en_otro_lugar_observacion' => '<script>alert(1)</script>']);
        $html = $this->render([$perfil], ['filters' => ['area_desempeno_id' => 1, 'mencion' => '<svg onload=alert(2)>']]);
        $this->assertStringNotContainsString('<script>alert', $html); $this->assertStringNotContainsString('<img src=x', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html); $this->assertStringContainsString('&lt;svg', $html);
    }

    public function test_errores_y_entrada_malformada_no_rompen_el_formulario(): void
    {
        session()->flashInput(['nombre' => ['entrada' => 'inválida'], 'area_desempeno_id' => 2]);
        $errores = new ViewErrorBag; $errores->put('default', new MessageBag(['nombre' => 'El nombre debe ser texto.']));
        $html = $this->render([], ['errors' => $errores]); $xpath = $this->xpath($html);
        $this->assertStringContainsString('El nombre debe ser texto.', $html);
        $this->assertSame(1, $xpath->query('//*[@data-slep-errors and @role="alert"]')->length);
        $this->assertSame(1, $xpath->query('//input[@name="nombre" and @value="" and @aria-invalid="true"]')->length);
        $this->assertSame(0, $xpath->query('//*[@data-slep-conditional and @hidden]')->length);
    }

    private function profile(): PostulantProfile
    {
        $perfil = (new PostulantProfile)->forceFill(['id' => 10]);
        $user = (new User)->forceFill(['id' => 1, 'rut' => '99000001-K', 'nombres' => 'Persona sintética']);
        $this->complete($perfil, $user); $perfil->setRelation('contratosLaboralesActivos', collect());
        return $perfil;
    }

    private function complete(PostulantProfile $perfil, User $user): void
    {
        $user->setRelation('communes', collect([(new Commune)->forceFill(['id' => 1, 'name' => 'Comuna sintética'])]));
        $perfil->setRelation('user', $user)->setRelation('areaDesempeno', (new AreaDesempeno)->forceFill(['nombre' => 'Área sintética']))
            ->setRelation('reemplazosActivosActuales', collect());
    }

    private function contrato(int $rbd = 99001, int $horas = 30): PostulantProfileContrato
    {
        return (new PostulantProfileContrato)->forceFill(['tipo_contrato' => 'Plazo Fijo', 'cantidad_horas' => $horas, 'fecha_termino' => '2026-12-31'])
            ->setRelation('establecimiento', (new Establecimiento)->forceFill(['rbd' => $rbd, 'nombre_establecimiento' => 'Escuela sintética '.$rbd]));
    }

    private function render(array $perfiles, array $extra = []): string
    {
        return view('reemplazos.buscador-postulantes.index', $extra + [
            'profiles' => new LengthAwarePaginator($perfiles, count($perfiles), 15), 'filters' => [],
            'errors' => new ViewErrorBag,
            'communes' => collect([(object) ['id' => 1, 'name' => 'Comuna sintética']]),
            'areasDocente' => collect([(object) ['id' => 1, 'nombre' => 'Docente Básica'], (object) ['id' => 2, 'nombre' => 'Docente Técnico Profesional']]),
            'areasAsistente' => collect([(object) ['id' => 3, 'nombre' => 'Asistente sintético']]),
            'areaDocenteIds' => collect([1, 2]), 'areaTpIds' => collect([2]), 'especialidadesTp' => collect(['Electricidad']), 'mencionesDocentes' => collect(['Matemática']),
        ])->render();
    }

    private function xpath(string $html): \DOMXPath
    {
        $dom = new \DOMDocument; @$dom->loadHTML('<?xml encoding="UTF-8">'.$html);
        return new \DOMXPath($dom);
    }
}
