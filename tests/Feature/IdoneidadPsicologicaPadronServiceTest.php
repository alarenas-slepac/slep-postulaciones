<?php

namespace Tests\Feature;

use App\Services\IdoneidadPsicologica\IdoneidadPsicologicaPadronService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class IdoneidadPsicologicaPadronServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('establecimientos', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('rbd')->nullable();
            $table->string('nombre_establecimiento');
            $table->string('comuna')->nullable();
        });
        Schema::create('reemplazos_personal', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('establecimiento_id')->nullable();
            $table->string('rut')->nullable();
            $table->string('nombre')->nullable();
            $table->date('fecha_ingreso')->nullable();
            $table->date('fecha_termino')->nullable();
            $table->string('tipocontrato')->nullable();
            $table->string('estatuto')->nullable();
            $table->string('escalafon')->nullable();
            $table->unsignedSmallInteger('anio');
            $table->unsignedTinyInteger('mes');
            $table->boolean('vigente')->default(true);
            $table->timestamps();
        });
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('rut')->nullable();
            $table->timestamp('deleted_at')->nullable();
        });
        Schema::create('areas_desempeno', function (Blueprint $table): void {
            $table->id();
            $table->string('nombre');
        });
        Schema::create('postulant_profiles', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('area_desempeno_id')->nullable();
        });
        Schema::create('solicitudes_reemplazo', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('area_desempeno_id')->nullable();
            $table->unsignedBigInteger('postulant_profile_id')->nullable();
            $table->unsignedBigInteger('contrato_trabajo_postulant_profile_id')->nullable();
            $table->string('rut_reemplazo_normalizado')->nullable();
            $table->string('numero_solicitud')->nullable();
            $table->string('estado');
            $table->date('fecha_inicio');
            $table->date('fecha_inicio_trabajo')->nullable();
            $table->date('fecha_termino');
        });
        Schema::create('idoneidad_psicologica_funcionarios', function (Blueprint $table): void {
            $table->id();
            $table->string('rut_normalizado');
            $table->string('cargo_clave')->nullable();
            $table->string('cargo_funcion')->nullable();
        });

        DB::table('establecimientos')->insert([
            ['id' => 1, 'rbd' => 111, 'nombre_establecimiento' => 'Escuela Uno', 'comuna' => 'Coronel'],
            ['id' => 2, 'rbd' => 222, 'nombre_establecimiento' => 'Escuela Dos', 'comuna' => 'Lota'],
        ]);
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('reemplazos_personal');
        Schema::dropIfExists('establecimientos');
        Schema::dropIfExists('idoneidad_psicologica_funcionarios');
        Schema::dropIfExists('solicitudes_reemplazo');
        Schema::dropIfExists('postulant_profiles');
        Schema::dropIfExists('areas_desempeno');
        Schema::dropIfExists('users');

        parent::tearDown();
    }

    public function test_usa_solo_ultimo_padron_vigente_y_filtra_aaee_contratos_elegibles(): void
    {
        DB::table('reemplazos_personal')->insert([
            $this->fila(1, '11.111.111-1', 'AAEE histórico', '2026-08-01', 'Plazo Fijo', 'AAEE', 'Administrativo', 2026, 8),
            $this->fila(1, '11.111.111-1', 'AAEE vigente', '2026-09-02', 'Plazo Fijo', 'AAEE', 'Administrativo', 2026, 9),
            $this->fila(1, '12.222.222-2', 'Docente vigente', '2026-09-03', 'Reemplazo', 'Docente', 'Profesor', 2026, 9),
            $this->fila(1, '13.333.333-3', 'AAEE titular', '2026-09-03', 'Titular', 'Asistente de la Educación', 'Auxiliar', 2026, 9),
            $this->fila(1, '14.444.444-4', 'AAEE fuera de rango', '2026-08-30', 'Suplencia', 'Asistente', 'Paradocente', 2026, 9),
            $this->fila(2, '15.555.555-5', 'AAEE suplencia', '2026-09-15', 'Suplencia', 'Asistente de la Educación', 'Paradocente', 2026, 9),
        ]);

        $funcionarios = app(IdoneidadPsicologicaPadronService::class)->funcionariosElegibles(
            Carbon::parse('2026-09-01'),
            Carbon::parse('2026-09-30')
        );

        $this->assertSame(['AAEE vigente'], $funcionarios->pluck('nombre')->all());
        $this->assertSame([1], $funcionarios->pluck('establecimiento_id')->all());
    }

    public function test_resuelve_cargo_y_excluye_a_quien_ya_fue_solicitado_para_el_mismo_cargo(): void
    {
        DB::table('areas_desempeno')->insert([
            ['id' => 1, 'nombre' => 'Auxiliar de aseo'],
            ['id' => 2, 'nombre' => 'Inspector de patio'],
        ]);
        DB::table('users')->insert(['id' => 1, 'rut' => '123456785']);
        DB::table('postulant_profiles')->insert(['id' => 1, 'user_id' => 1, 'area_desempeno_id' => 1]);
        DB::table('solicitudes_reemplazo')->insert([
            'id' => 1,
            'area_desempeno_id' => 2,
            'rut_reemplazo_normalizado' => '111111111',
            'numero_solicitud' => '00001-2026',
            'estado' => 'aceptada',
            'fecha_inicio' => '2026-09-01',
            'fecha_inicio_trabajo' => '2026-09-03',
            'fecha_termino' => '2026-09-30',
        ]);
        DB::table('reemplazos_personal')->insert([
            $this->fila(1, '12.345.678-5', 'AAEE plazo fijo', '2026-09-02', 'Plazo Fijo', 'AAEE', 'Escalafón no usado', 2026, 9),
            $this->fila(2, '11.111.111-1', 'AAEE reemplazo', '2026-09-03', 'Reemplazo', 'AAEE', 'Escalafón no usado', 2026, 9),
        ]);

        $service = app(IdoneidadPsicologicaPadronService::class);
        $funcionarios = $service->funcionariosElegibles(Carbon::parse('2026-09-01'), Carbon::parse('2026-09-30'));

        $this->assertSame(['AAEE reemplazo', 'AAEE plazo fijo'], $funcionarios->pluck('nombre')->all());
        $this->assertSame(['Inspector de patio', 'Auxiliar de aseo'], $funcionarios->pluck('cargo_idoneidad')->all());
        $this->assertSame('11.111.111-1', $service->formatoRut('111111111'));

        DB::table('idoneidad_psicologica_funcionarios')->insert([
            'rut_normalizado' => '12345678',
            'cargo_clave' => 'AUXILIAR_DE_ASEO',
        ]);

        $this->assertSame(['AAEE reemplazo'], $service->funcionariosElegibles(Carbon::parse('2026-09-01'), Carbon::parse('2026-09-30'))->pluck('nombre')->all());
    }

    private function fila(int $establecimientoId, string $rut, string $nombre, string $fechaIngreso, string $contrato, string $estatuto, string $escalafon, int $anio, int $mes): array
    {
        return [
            'establecimiento_id' => $establecimientoId,
            'rut' => $rut,
            'nombre' => $nombre,
            'fecha_ingreso' => $fechaIngreso,
            'tipocontrato' => $contrato,
            'estatuto' => $estatuto,
            'escalafon' => $escalafon,
            'anio' => $anio,
            'mes' => $mes,
            'vigente' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
