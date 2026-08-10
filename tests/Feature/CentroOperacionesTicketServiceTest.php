<?php

namespace Tests\Feature;

use App\Mail\CentroOperacionesTicketMail;
use App\Models\CentroOperacionesIncidencia;
use App\Models\CentroOperacionesIncidenteConfiguracion;
use App\Models\FuncionarioAcAutorizado;
use App\Models\User;
use App\Services\CentroOperaciones\TicketService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CentroOperacionesTicketServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->crearEsquema();
    }

    protected function tearDown(): void
    {
        foreach ([
            'centro_operaciones_tickets',
            'centro_operaciones_incidente_configuraciones',
            'centro_operaciones_incidencias',
            'centro_operaciones_reportes',
            'funcionarios_ac_autorizados',
            'establecimientos',
            'users',
        ] as $tabla) {
            Schema::dropIfExists($tabla);
        }

        parent::tearDown();
    }

    public function test_crea_un_ticket_con_dos_responsables_y_notifica_a_ambos(): void
    {
        Mail::fake();
        [$usuario, $incidencia] = $this->crearContexto('corte_agua');
        $principal = $this->crearFuncionario('principal@slep.test', 'Subdirección A');
        $segundo = $this->crearFuncionario('segundo@slep.test', 'Subdirección B');

        CentroOperacionesIncidenteConfiguracion::query()->create([
            'tipo' => 'corte_agua',
            'nombre' => 'Corte de agua',
            'severidad' => 'critico',
            'unidad_departamento' => 'Servicios Generales',
            'subdireccion_dependencia' => 'Subdirección A',
            'responsable_funcionario_ac_id' => $principal->id,
            'segunda_subdireccion_responsable' => 'Subdirección B',
            'segundo_responsable_funcionario_ac_id' => $segundo->id,
            'plazo_dias' => 4,
            'activo' => true,
        ]);

        $ticket = app(TicketService::class)->crearParaIncidencia($incidencia, $usuario);

        $this->assertSame('asignado', $ticket->estado);
        $this->assertSame($principal->id, $ticket->responsable_funcionario_ac_id);
        $this->assertSame($segundo->id, $ticket->segundo_responsable_funcionario_ac_id);
        $this->assertNotNull($ticket->vence_en);
        $this->assertDatabaseCount('centro_operaciones_tickets', 1);
        Mail::assertQueued(CentroOperacionesTicketMail::class, 2);
    }

    public function test_crea_un_unico_ticket_pendiente_aunque_no_exista_asignacion(): void
    {
        Mail::fake();
        [$usuario, $incidencia] = $this->crearContexto('otro');
        $servicio = app(TicketService::class);

        $primero = $servicio->crearParaIncidencia($incidencia, $usuario);
        $segundo = $servicio->crearParaIncidencia($incidencia, $usuario);

        $this->assertSame($primero->id, $segundo->id);
        $this->assertSame('pendiente_asignacion', $primero->estado);
        $this->assertNull($primero->responsable_funcionario_ac_id);
        $this->assertNull($primero->vence_en);
        $this->assertDatabaseCount('centro_operaciones_tickets', 1);
        Mail::assertQueued(CentroOperacionesTicketMail::class, 0);
    }

    public function test_un_ticket_pendiente_se_asigna_al_completar_el_mantenedor(): void
    {
        Mail::fake();
        [$usuario, $incidencia] = $this->crearContexto('sismo');
        $servicio = app(TicketService::class);
        $ticket = $servicio->crearParaIncidencia($incidencia, $usuario);
        $responsable = $this->crearFuncionario('responsable@slep.test', 'Subdirección Operativa');
        $configuracion = CentroOperacionesIncidenteConfiguracion::query()->create([
            'tipo' => 'sismo',
            'nombre' => 'Sismo',
            'severidad' => 'alerta',
            'unidad_departamento' => 'Infraestructura',
            'subdireccion_dependencia' => 'Subdirección Operativa',
            'responsable_funcionario_ac_id' => $responsable->id,
            'plazo_dias' => 4,
            'activo' => true,
        ]);

        $this->assertSame(1, $servicio->sincronizarAsignaciones($configuracion));

        $ticket->refresh();
        $this->assertSame('asignado', $ticket->estado);
        $this->assertSame($responsable->id, $ticket->responsable_funcionario_ac_id);
        $this->assertNotNull($ticket->vence_en);
        Mail::assertQueued(CentroOperacionesTicketMail::class, 1);
    }

    /** @return array{User, CentroOperacionesIncidencia} */
    private function crearContexto(string $tipo): array
    {
        DB::table('users')->insert(['id' => 1]);
        DB::table('establecimientos')->insert(['id' => 1, 'nombre_establecimiento' => 'Escuela de prueba']);
        DB::table('centro_operaciones_reportes')->insert([
            'id' => 1,
            'establecimiento_id' => 1,
            'reportado_por_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('centro_operaciones_incidencias')->insert([
            'id' => 1,
            'reporte_id' => 1,
            'establecimiento_id' => 1,
            'fecha_incidencia' => now()->toDateString(),
            'tipo' => $tipo,
            'severidad' => 'alerta',
            'estado' => 'activa',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [User::query()->findOrFail(1), CentroOperacionesIncidencia::query()->findOrFail(1)];
    }

    private function crearFuncionario(string $email, string $subdireccion): FuncionarioAcAutorizado
    {
        return FuncionarioAcAutorizado::query()->create([
            'nombres' => 'Responsable',
            'apellido_paterno' => 'Prueba',
            'email' => $email,
            'unidad_departamento' => 'Unidad de prueba',
            'subdireccion_dependencia' => $subdireccion,
            'estado_autorizacion' => 'activo',
        ]);
    }

    private function crearEsquema(): void
    {
        Schema::create('users', fn (Blueprint $table) => $table->id());
        Schema::create('establecimientos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre_establecimiento');
        });
        Schema::create('funcionarios_ac_autorizados', function (Blueprint $table) {
            $table->id();
            $table->string('nombres')->nullable();
            $table->string('apellido_paterno')->nullable();
            $table->string('apellido_materno')->nullable();
            $table->string('email')->nullable();
            $table->string('unidad_departamento')->nullable();
            $table->string('subdireccion_dependencia')->nullable();
            $table->string('estado_autorizacion')->nullable();
            $table->date('fecha_inicio_autorizacion')->nullable();
            $table->date('fecha_fin_autorizacion')->nullable();
            $table->foreignId('registered_user_id')->nullable();
            $table->timestamps();
        });
        Schema::create('centro_operaciones_reportes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('establecimiento_id');
            $table->foreignId('reportado_por_id')->nullable();
            $table->timestamps();
        });
        Schema::create('centro_operaciones_incidencias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reporte_id');
            $table->foreignId('establecimiento_id')->nullable();
            $table->date('fecha_incidencia');
            $table->string('tipo', 48);
            $table->string('severidad', 16);
            $table->text('descripcion')->nullable();
            $table->string('estado', 16);
            $table->timestamps();
        });
        Schema::create('centro_operaciones_incidente_configuraciones', function (Blueprint $table) {
            $table->id();
            $table->string('tipo', 80)->unique();
            $table->string('nombre')->nullable();
            $table->string('severidad')->nullable();
            $table->string('unidad_departamento')->nullable();
            $table->string('subdireccion_dependencia')->nullable();
            $table->foreignId('responsable_funcionario_ac_id')->nullable();
            $table->string('segunda_subdireccion_responsable')->nullable();
            $table->foreignId('segundo_responsable_funcionario_ac_id')->nullable();
            $table->unsignedSmallInteger('plazo_dias')->default(4);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
        Schema::create('centro_operaciones_tickets', function (Blueprint $table) {
            $table->id();
            $table->string('numero')->nullable()->unique();
            $table->foreignId('incidencia_id')->unique();
            $table->foreignId('configuracion_id')->nullable();
            $table->string('unidad_departamento')->nullable();
            $table->string('subdireccion_dependencia')->nullable();
            $table->foreignId('responsable_funcionario_ac_id')->nullable();
            $table->string('segunda_subdireccion_responsable')->nullable();
            $table->foreignId('segundo_responsable_funcionario_ac_id')->nullable();
            $table->foreignId('creado_por_id')->nullable();
            $table->timestamp('vence_en')->nullable();
            $table->string('estado')->default('asignado');
            $table->timestamp('notificado_responsable_en')->nullable();
            $table->timestamp('escalado_en')->nullable();
            $table->timestamp('resuelto_en')->nullable();
            $table->foreignId('resuelto_por_id')->nullable();
            $table->text('resolucion')->nullable();
            $table->timestamps();
        });
    }
}
