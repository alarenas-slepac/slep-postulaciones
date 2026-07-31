<?php

namespace Tests\Feature;

use App\Services\Certificados\CertificadoVigenciaLaboralService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CertificadoVigenciaFuncionarioAcTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->crearTablas();
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('funcionarios_ac_autorizados');
        Schema::dropIfExists('certificado_contratos_historicos');
        Schema::dropIfExists('certificado_importaciones');
        parent::tearDown();
    }

    public function test_identifica_funcionario_ac_solo_con_autorizacion_activa(): void
    {
        $importacionId = DB::table('certificado_importaciones')->insertGetId([
            'estado' => 'procesado',
            'es_vigente' => true,
            'activado_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('certificado_contratos_historicos')->insert([
            'importacion_id' => $importacionId,
            'fila_origen' => 2,
            'rut_normalizado' => '123456785',
            'nombre' => 'FUNCIONARIA DE PRUEBA',
            'establecimiento' => 'ADMINISTRACIÓN CENTRAL',
            'comuna' => 'CORONEL',
            'fecha_ingreso' => '2025-01-01',
            'fecha_finiquito' => '2027-12-31',
            'termino_indefinido' => false,
            'calidad_juridica' => 'CONTRATA',
            'regimen_juridico' => 'CÓDIGO DEL TRABAJO',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('funcionarios_ac_autorizados')->insert([
            'rut_normalizado' => '123456785',
            'estado_autorizacion' => 'activo',
        ]);

        $service = app(CertificadoVigenciaLaboralService::class);

        self::assertTrue($service->resolver('12.345.678-5')['es_funcionario_ac']);

        DB::table('funcionarios_ac_autorizados')
            ->where('rut_normalizado', '123456785')
            ->update(['estado_autorizacion' => 'inactivo']);

        self::assertFalse($service->resolver('12.345.678-5')['es_funcionario_ac']);
    }

    private function crearTablas(): void
    {
        Schema::create('certificado_importaciones', function (Blueprint $table) {
            $table->id();
            $table->string('estado');
            $table->boolean('es_vigente')->default(false);
            $table->timestamp('activado_at')->nullable();
            $table->timestamps();
        });

        Schema::create('certificado_contratos_historicos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('importacion_id');
            $table->unsignedInteger('fila_origen');
            $table->string('rut_normalizado');
            $table->string('nombre');
            $table->string('establecimiento');
            $table->string('comuna');
            $table->date('fecha_ingreso');
            $table->date('fecha_finiquito')->nullable();
            $table->boolean('termino_indefinido')->default(false);
            $table->string('calidad_juridica');
            $table->string('regimen_juridico');
            $table->timestamps();
        });

        Schema::create('funcionarios_ac_autorizados', function (Blueprint $table) {
            $table->id();
            $table->string('rut_normalizado')->unique();
            $table->string('estado_autorizacion');
        });
    }
}
