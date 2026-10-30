<?php

namespace Tests\Feature;

use App\Http\Controllers\PostulantProfileController;
use App\Models\Mencion;
use App\Models\PostulantProfile;
use App\Models\Subsector;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Tests\Support\IsolatedSecurityTestCase;

class PostulantMencionSelectorTest extends IsolatedSecurityTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        (require database_path('migrations/2025_09_17_131301_create_subsectores_table.php'))->up();
        (require database_path('migrations/2025_09_17_131310_create_menciones_table.php'))->up();
        Schema::create('areas_desempeno', function (Blueprint $table): void {
            $table->id(); $table->string('nombre'); $table->string('estamento');
            $table->string('slug')->nullable(); $table->boolean('activo')->default(true);
        });
        Schema::create('communes', function (Blueprint $table): void {
            $table->id(); $table->string('name'); $table->string('region_code');
        });
        Schema::create('commune_user', function (Blueprint $table): void {
            $table->unsignedBigInteger('user_id'); $table->unsignedBigInteger('commune_id');
        });
        $this->app['request']->setLaravelSession($this->app['session.store']);
    }

    public function test_perfil_y_api_incluyen_todo_el_catalogo_sin_limite_de_300(): void
    {
        $subsector = Subsector::create(['subsector' => 'Lenguaje']);
        $otro = Subsector::create(['subsector' => 'Ciencias']);
        $rows = [];
        for ($i = 1; $i <= 350; $i++) {
            $rows[] = ['nombre' => sprintf('Mención %03d', $i), 'universidad' => 'Universidad de prueba',
                'anio' => 2020, 'subsector_id' => $subsector->id];
        }
        DB::table('menciones')->insert($rows);
        Mencion::create(['nombre' => 'Ciencias naturales', 'subsector_id' => $otro->id]);

        $user = $this->testUser();
        $user->setRelation('postulantProfile', new PostulantProfile(['user_id' => $user->id]));
        $this->actingAs($user);
        $request = Request::create('/postulante/perfil');
        $request->setUserResolver(fn () => $user);
        $view = (new PostulantProfileController)->edit($request);
        $menciones = $view->getData()['menciones'];
        $this->assertSame(351, $menciones->flatten(1)->count());
        $this->assertSame(['Lenguaje', 'Ciencias'], $menciones->keys()->all());
        $xpath = $this->xpath($this->renderSelector($menciones));
        $this->assertSame(352, $xpath->query('//select[@id="mencion"]/descendant::option')->length);

        $this->getJson(route('api.menciones.search'))->assertOk()
            ->assertJsonCount(2)->assertJsonCount(350, '0.items')->assertJsonCount(1, '1.items');
        $this->getJson(route('api.menciones.search', ['q' => '350']))->assertOk()
            ->assertJsonCount(1, '0.items')->assertJsonPath('0.items.0.value', 'Mención 350 - Universidad de prueba - 2020');
    }

    public function test_conserva_el_valor_guardado_y_no_duplica_una_opcion_del_catalogo(): void
    {
        $subsector = Subsector::create(['subsector' => 'Lenguaje']);
        $mencion = Mencion::create(['nombre' => 'Lenguaje', 'universidad' => 'Universidad de prueba',
            'anio' => 2020, 'subsector_id' => $subsector->id]);
        $catalogo = collect(['Lenguaje' => collect([$mencion])]);
        $xpath = $this->xpath($this->renderSelector($catalogo, $mencion->etiqueta));
        $this->assertSame(2, $xpath->query('//option')->length);
        $this->assertSame($mencion->etiqueta, $xpath->query('//option[@selected]')->item(0)->getAttribute('value'));

        $xpath = $this->xpath($this->renderSelector($catalogo, 'Mención histórica'));
        $this->assertSame(3, $xpath->query('//option')->length);
        $this->assertSame('Mención histórica', $xpath->query('//option[@selected]')->item(0)->getAttribute('value'));
    }

    public function test_restaura_old_y_asocia_busqueda_ayuda_y_error_sin_buscador_externo(): void
    {
        $this->app['session.store']->flashInput(['mencion' => 'Valor enviado anteriormente']);
        $errors = (new ViewErrorBag)->put('default', new MessageBag(['mencion' => 'Revisa la mención seleccionada.']));
        $html = $this->renderSelector(collect(), 'Valor guardado', $errors);
        $xpath = $this->xpath($html);
        $this->assertSame('Valor enviado anteriormente', $xpath->query('//option[@selected]')->item(0)->getAttribute('value'));
        $this->assertSame(1, $xpath->query('//select[@id="mencion" and @name="mencion" and @data-slep-select="search" and @aria-invalid="true"]')->length);
        $this->assertSame('mencion-ayuda mencion-error', $xpath->query('//select')->item(0)->getAttribute('aria-describedby'));
        $this->assertStringContainsString('Revisa la mención seleccionada.', $html);
        $this->assertSame(0, $xpath->query('//input')->length);
    }

    public function test_escapa_etiquetas_y_valores_y_permite_un_catalogo_vacio(): void
    {
        $mencion = new Mencion(['nombre' => '<script>alert(1)</script>', 'universidad' => 'Universidad "Prueba"']);
        $html = $this->renderSelector(collect(['<img src=x onerror=alert(2)>' => collect([$mencion])]));
        $xpath = $this->xpath($html);
        $this->assertSame(0, $xpath->query('//script|//img')->length);
        $this->assertSame($mencion->etiqueta, $xpath->query('//optgroup/option')->item(0)->getAttribute('value'));
        $html = $this->renderSelector(collect());
        $this->assertStringContainsString('No hay menciones registradas', $html);
        $this->assertSame(1, $this->xpath($html)->query('//option')->length);
    }

    public function test_el_catalogo_sigue_requiriendo_autenticacion(): void
    {
        $this->getJson(route('api.menciones.search'))->assertUnauthorized();
    }

    private function renderSelector($menciones, string $selected = '', ?ViewErrorBag $errors = null): string
    {
        return view('postulant.profile.partials.mencion', [
            'menciones' => $menciones,
            'profile' => new PostulantProfile(['mencion' => $selected]),
            'errors' => $errors ?? new ViewErrorBag,
        ])->render();
    }

    private function xpath(string $html): \DOMXPath
    {
        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$html);

        return new \DOMXPath($dom);
    }
}
