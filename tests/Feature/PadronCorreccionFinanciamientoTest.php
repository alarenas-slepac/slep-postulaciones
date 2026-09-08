<?php

namespace Tests\Feature;

use App\Models\PadronRevision;
use App\Models\SolicitudReemplazo;
use App\Services\Padron\PadronAplicacionService;
use App\Services\Padron\PadronConciliador;
use App\Services\Padron\PadronConflictosAsignacionService;
use App\Services\Padron\PadronHistorialService;
use App\Services\Padron\PadronRevisionService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PadronCorreccionFinanciamientoTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        Schema::create('establecimientos', function (Blueprint $t) {
            $t->id(); $t->integer('rbd'); $t->string('nombre_establecimiento');
        });
        Schema::create('reemplazos_personal', function (Blueprint $t) {
            $t->id(); $t->integer('establecimiento_id'); $t->integer('rbd');
            foreach (['rut', 'nombre', 'tipocontrato', 'estatuto', 'escalafon', 'financiamiento'] as $campo) { $t->string($campo); }
            foreach (['fecha_ingreso', 'fecha_antiguedad'] as $campo) { $t->date($campo)->nullable(); }
            foreach (['anio', 'mes', 'jornada', 'jornada_basica', 'jornada_media'] as $campo) { $t->integer($campo); }
            $t->boolean('vigente')->default(true); $t->string('row_hash')->unique();
            $t->string('source_filename')->nullable(); $t->timestamps();
        });
        Schema::create('dotacion_docente_asignaciones', function (Blueprint $t) {
            $t->id(); $t->integer('anio'); $t->integer('establecimiento_id');
            $t->foreignId('reemplazos_personal_id')->nullable()->constrained('reemplazos_personal');
            $t->string('docente_rut'); $t->string('estado'); $t->decimal('horas_contrato');
        });
        Schema::create('declaracion_sostenedores', function (Blueprint $t) {
            $t->id(); $t->string('rut'); $t->integer('rbd'); $t->string('estamento'); $t->integer('horas_contratadas');
        });
        foreach (PadronHistorialService::DOCUMENTOS as $table) {
            Schema::create($table, function (Blueprint $t) {
                $t->id(); $t->foreignId('reemplazo_personal_id')->constrained('reemplazos_personal');
                $t->string('estado')->default('cerrado'); $t->timestamps();
            });
        }
        foreach (['2026_09_08_120000_create_padron_revisiones.php', '2026_09_08_130000_add_padron_aplicacion_segura.php',
            '2026_09_08_150000_add_padron_snapshot_to_documentos.php', '2026_09_08_160000_create_padron_periodo_versiones.php'] as $migration) {
            (require base_path('database/migrations/'.$migration))->up();
        }
        DB::table('establecimientos')->insert(['id' => 1, 'rbd' => 99999, 'nombre_establecimiento' => 'Escuela sintética']);
        foreach ([['SUB.GENERAL', 21], ['PIE', 2], ['SEP', 17]] as $i => [$funding, $hours]) {
            DB::table('reemplazos_personal')->insert(array_replace($this->data($funding, $hours), [
                'id' => $i + 101, 'establecimiento_id' => 1, 'mes' => 8, 'row_hash' => 'sintetico-'.($i + 101),
                'tipocontrato' => $i === 0 ? 'PLANTA' : 'PLANTA '.$funding,
                'escalafon' => $i === 0 ? 'DOCENTE AULA' : 'DOCENTE '.$funding,
                'created_at' => '2026-01-01 00:00:00', 'updated_at' => '2026-08-01 00:00:00',
            ]));
        }
        foreach ([7, 7, 2, 2, 1, 1, 2, 6] as $i => $hours) {
            DB::table('dotacion_docente_asignaciones')->insert([
                'id' => $i + 501, 'anio' => 2026, 'establecimiento_id' => 1, 'reemplazos_personal_id' => null,
                'docente_rut' => '111111111', 'estado' => 'activa', 'horas_contrato' => $hours,
            ]);
        }
        DB::table('declaracion_sostenedores')->insert(['rut' => '111111111', 'rbd' => 99999, 'estamento' => 'DOCENTE', 'horas_contratadas' => 40]);
        for ($i = 1; $i <= 6; $i++) {
            DB::table('solicitudes_reemplazo')->insert(['id' => $i, 'reemplazo_personal_id' => 103, 'estado' => 'cerrado', 'updated_at' => '2026-08-01 00:00:00']);
        }
    }

    private function data(string $funding, int $hours): array
    {
        return ['rut' => '111111111', 'nombre' => 'Persona sintética', 'rbd' => 99999,
            'tipocontrato' => 'PLANTA', 'financiamiento' => $funding, 'estatuto' => 'DOCENTE', 'escalafon' => 'DOCENTE AULA',
            'anio' => 2026, 'mes' => 9, 'fecha_ingreso' => '2020-03-01', 'fecha_antiguedad' => '2010-01-01',
            'jornada' => $hours, 'jornada_basica' => $hours, 'jornada_media' => 0];
    }

    public function test_correction_resolves_assignments_and_updates_contracts_without_replacing_ids_or_history(): void
    {
        $before = DB::table('dotacion_docente_asignaciones')->orderBy('id')->get()->toJson();
        $incoming = [$this->data('SEP', 17), $this->data('SUB.GENERAL', 21), $this->data('PIE', 2)];
        $service = app(PadronRevisionService::class);
        $snapshot = (new \ReflectionMethod($service, 'snapshot'))->invoke($service, 202609);
        $report = app(PadronConciliador::class)->reconcile(
            array_map(fn ($data, $i) => ['datos' => $data, 'fila_excel' => $i + 2, 'observaciones' => []], $incoming, array_keys($incoming)),
            $snapshot['personal'], $snapshot['establecimientos'], $snapshot['asignaciones'], $snapshot['declaraciones'],
        );
        $revision = PadronRevision::create(['archivo' => 'sintetico.xlsx', 'archivo_hash' => str_repeat('a', 64),
            'base_hash' => $snapshot['hash'], 'created_by' => 1, 'anio' => 2026, 'mes' => 9,
            'resumen' => $report['resumen'], 'errores' => $report['errores'], 'excesos' => $report['excesos']]);
        $revision->filas()->createMany($report['filas']);
        $this->assertSame([103, 101, 102], array_column($report['filas'], 'personal_id'));
        $diagnosis = app(PadronConflictosAsignacionService::class)->analizar($revision);
        $this->assertSame(8, $diagnosis['asignaciones_revisadas']);
        $this->assertSame(0, $diagnosis['bloqueantes']);
        $this->assertSame([], $diagnosis['errores']);

        // Instancia aislada de prueba; no cambia la constante ni el binding de la aplicación.
        $writer = new class($service) extends PadronAplicacionService {
            public function disponible(): bool
            {
                return app()->environment('testing') && DB::connection()->getDatabaseName() === ':memory:';
            }
        };
        $plan = $writer->plan($revision);
        $this->assertSame([], $plan['bajas']);
        $this->assertSame([], $plan['errores']);
        $writer->aplicar($revision, 7, $plan['confirmacion_hash']);
        $this->assertSame([101, 102, 103], DB::table('reemplazos_personal')->orderBy('id')->pluck('id')->all());
        foreach ([101 => 'SUB.GENERAL', 102 => 'PIE', 103 => 'SEP'] as $id => $funding) {
            $this->assertDatabaseHas('reemplazos_personal', ['id' => $id, 'tipocontrato' => 'PLANTA',
                'financiamiento' => $funding, 'escalafon' => 'DOCENTE AULA', 'vigente' => true, 'row_hash' => 'sintetico-'.$id]);
        }
        $this->assertSame(40, (int) DB::table('reemplazos_personal')->sum('jornada'));
        $this->assertSame($before, DB::table('dotacion_docente_asignaciones')->orderBy('id')->get()->toJson());
        $this->assertDatabaseCount('solicitudes_reemplazo', 6);
        foreach (SolicitudReemplazo::all() as $document) {
            $this->assertSame(103, (int) $document->reemplazo_personal_id);
            $this->assertSame('PLANTA SEP', $document->funcionarioTitular->tipocontrato);
            $this->assertSame('DOCENTE SEP', $document->funcionarioTitular->escalafon);
            $this->assertSame('cerrado', $document->estado);
            $this->assertSame('2026-08-01 00:00:00', $document->updated_at->format('Y-m-d H:i:s'));
        }
        $this->assertFalse(app(PadronAplicacionService::class)->disponible());
    }
}
