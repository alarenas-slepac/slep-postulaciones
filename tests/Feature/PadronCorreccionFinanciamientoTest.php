<?php

namespace Tests\Feature;

use App\Models\PadronRevision;
use App\Models\SolicitudReemplazo;
use App\Services\Padron\PadronAplicacionService;
use App\Services\Padron\PadronConciliador;
use App\Services\Padron\PadronConflictosAsignacionService;
use App\Services\Padron\PadronHistorialService;
use App\Services\Padron\PadronRevisionService;
use App\Services\Padron\PadronResolucionService;
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

    public static function contractLabels(): array
    {
        return [['PLANTA'], ['CONTRATA'], ['INDEFINIDO'], ['PLAZO FIJO']];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('contractLabels')]
    public function test_correction_resolves_assignments_and_updates_contracts_without_replacing_ids_or_history(string $base): void
    {
        if ($base !== 'PLANTA') {
            foreach ([101 => 'SUB.GENERAL', 102 => 'PIE', 103 => 'SEP'] as $id => $funding) {
                DB::table('reemplazos_personal')->where('id', $id)->update([
                    'tipocontrato' => $id === 101 ? $base : $base.' '.$funding, 'escalafon' => 'DOCENTE AULA',
                ]);
            }
        }
        $before = DB::table('dotacion_docente_asignaciones')->orderBy('id')->get()->toJson();
        $incoming = array_map(fn ($row) => array_replace($row, ['tipocontrato' => $base]),
            [$this->data('SEP', 17), $this->data('SUB.GENERAL', 21), $this->data('PIE', 2)]);
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
            $this->assertDatabaseHas('reemplazos_personal', ['id' => $id, 'tipocontrato' => $base,
                'financiamiento' => $funding, 'escalafon' => 'DOCENTE AULA', 'vigente' => true, 'row_hash' => 'sintetico-'.$id]);
        }
        $this->assertSame(40, (int) DB::table('reemplazos_personal')->sum('jornada'));
        $this->assertSame($before, DB::table('dotacion_docente_asignaciones')->orderBy('id')->get()->toJson());
        $this->assertDatabaseCount('solicitudes_reemplazo', 6);
        foreach (SolicitudReemplazo::all() as $document) {
            $this->assertSame(103, (int) $document->reemplazo_personal_id);
            $this->assertSame($base.' SEP', $document->funcionarioTitular->tipocontrato);
            $this->assertSame($base === 'PLANTA' ? 'DOCENTE SEP' : 'DOCENTE AULA', $document->funcionarioTitular->escalafon);
            $this->assertSame('cerrado', $document->estado);
            $this->assertSame('2026-08-01 00:00:00', $document->updated_at->format('Y-m-d H:i:s'));
        }
        $this->assertFalse(app(PadronAplicacionService::class)->disponible());
    }

    private function redistribucionRevision(bool $vinculoDirecto = false): PadronRevision
    {
        DB::table('reemplazos_personal')->where('id', 101)->update(['jornada' => 26, 'jornada_basica' => 26]);
        DB::table('reemplazos_personal')->where('id', 103)->update(['jornada' => 4, 'jornada_basica' => 4]);
        DB::table('declaracion_sostenedores')->update(['horas_contratadas' => 32]);
        DB::table('solicitudes_reemplazo')->whereIn('id', [1, 2])->update(['reemplazo_personal_id' => 101]);
        DB::table('solicitudes_reemplazo')->whereIn('id', [3, 4])->update(['reemplazo_personal_id' => 102]);
        if ($vinculoDirecto) {
            DB::table('dotacion_docente_asignaciones')->where('id', 501)->update(['reemplazos_personal_id' => 102]);
        }
        $incoming = [$this->data('SEP', 4), $this->data('SUB.GENERAL', 28)];
        $service = app(PadronRevisionService::class);
        $snapshot = (new \ReflectionMethod($service, 'snapshot'))->invoke($service, 202609);
        $report = app(PadronConciliador::class)->reconcile(array_map(fn ($data, $i) => [
            'datos' => $data, 'fila_excel' => $i + 2, 'observaciones' => [],
        ], $incoming, array_keys($incoming)), $snapshot['personal'], $snapshot['establecimientos'], $snapshot['asignaciones'], $snapshot['declaraciones']);
        $revision = PadronRevision::create(['archivo' => 'redistribucion_sintetica.xlsx', 'archivo_hash' => str_repeat('b', 64),
            'base_hash' => $snapshot['hash'], 'created_by' => 1, 'anio' => 2026, 'mes' => 9,
            'resumen' => $report['resumen'], 'errores' => $report['errores'], 'excesos' => $report['excesos']]);
        $revision->filas()->createMany($report['filas']);
        return $revision;
    }

    public function test_redistribution_requires_both_decisions_and_preserves_history_on_test_application(): void
    {
        $revision = $this->redistribucionRevision();
        $before = DB::table('dotacion_docente_asignaciones')->orderBy('id')->get()->toJson();
        $writer = new class(app(PadronRevisionService::class)) extends PadronAplicacionService {
            public function disponible(): bool { return app()->environment('testing') && DB::connection()->getDatabaseName() === ':memory:'; }
        };
        $row = $revision->filas()->where('fila_excel', 3)->firstOrFail();
        $donor = $revision->filas()->whereNull('fila_excel')->where('personal_id', 102)->firstOrFail();
        $this->assertNull($row->personal_id);
        $this->assertNotEmpty($writer->plan($revision)['errores']);
        $resolution = app(PadronResolucionService::class);
        $resolution->resolver($revision, $row->id, 101, 'Conservar receptor por redistribución verificada.', 1);
        $plan = $writer->plan($revision);
        $this->assertStringContainsString('ID 102: confirme su baja', implode(' ', $plan['errores']));
        $this->assertSame([], $plan['bajas']);
        $this->assertSame(26, (int) DB::table('reemplazos_personal')->where('id', 101)->value('jornada'));
        $this->assertSame(1, (int) DB::table('reemplazos_personal')->where('id', 102)->value('vigente'));
        $resolution->resolver($revision, $donor->id, null, 'Confirmar baja propuesta de línea PIE absorbida.', 1);
        $plan = $writer->plan($revision);
        $this->assertSame([], $plan['errores']);
        $this->assertSame([102], $plan['bajas']);
        $this->assertSame(0, $plan['conflictos']['bloqueantes']);
        $writer->aplicar($revision, 1, $plan['confirmacion_hash']);
        $this->assertDatabaseCount('reemplazos_personal', 3);
        $this->assertDatabaseHas('reemplazos_personal', ['id' => 101, 'jornada' => 28, 'financiamiento' => 'SUB.GENERAL', 'vigente' => true]);
        $this->assertDatabaseHas('reemplazos_personal', ['id' => 102, 'jornada' => 2, 'financiamiento' => 'PIE', 'vigente' => false]);
        $this->assertSame(32, (int) DB::table('reemplazos_personal')->where('vigente', true)->sum('jornada'));
        $this->assertSame($before, DB::table('dotacion_docente_asignaciones')->orderBy('id')->get()->toJson());
        $this->assertSame(26, SolicitudReemplazo::findOrFail(1)->funcionarioTitular->jornada);
        $this->assertSame(2, SolicitudReemplazo::findOrFail(3)->funcionarioTitular->jornada);
        $this->assertSame('PLANTA PIE', SolicitudReemplazo::findOrFail(3)->funcionarioTitular->tipocontrato);
        $this->assertDatabaseCount('solicitudes_reemplazo', 6);
        $this->assertDatabaseCount('padron_revision_decisiones', 2);
        $this->assertFalse(app(PadronAplicacionService::class)->disponible());
    }

    public function test_redistribution_cannot_bypass_direct_assignment_to_absorbed_id(): void
    {
        $revision = $this->redistribucionRevision(true);
        $resolution = app(PadronResolucionService::class);
        $row = $revision->filas()->where('fila_excel', 3)->firstOrFail();
        $donor = $revision->filas()->whereNull('fila_excel')->where('personal_id', 102)->firstOrFail();
        $resolution->resolver($revision, $row->id, 101, 'Confirmar receptor de redistribución sintética.', 1);
        $resolution->resolver($revision, $donor->id, null, 'Confirmar propuesta de baja del absorbido.', 1);
        $plan = app(PadronAplicacionService::class)->plan($revision);
        $this->assertStringContainsString('ID contractual quedaría sin seleccionar', implode(' ', $plan['errores']));
        $this->assertSame(1, $plan['conflictos']['bloqueantes']);
        $this->assertDatabaseHas('dotacion_docente_asignaciones', ['id' => 501, 'reemplazos_personal_id' => 102]);
        $this->assertDatabaseHas('reemplazos_personal', ['id' => 102, 'vigente' => true]);
    }

    public function test_redistribution_is_visible_but_never_preselected_in_revision_form(): void
    {
        $revision = $this->redistribucionRevision();
        \Illuminate\Support\Facades\Storage::fake('local');
        \Illuminate\Support\Facades\Storage::disk('local')->put('views/layouts/app.blade.php', '@yield("content")');
        \Illuminate\Support\Facades\View::getFinder()->prependLocation(\Illuminate\Support\Facades\Storage::disk('local')->path('views'));
        \Illuminate\Support\Facades\View::share('errors', new \Illuminate\Support\ViewErrorBag);
        $request = \Illuminate\Http\Request::create('/', 'GET', ['revision' => $revision->id]);
        $html = app(\App\Http\Controllers\Reemplazos\PersonalImportController::class)->create($request)->render();
        $this->assertStringContainsString('Posible redistribución de financiamiento', $html);
        $this->assertStringContainsString('Conservar ID 101', $html);
        $this->assertStringContainsString('Proponer baja del ID 102', $html);
        $this->assertStringContainsString('Total del RUT: 32 → 32 h', $html);
        $this->assertStringContainsString('Seleccione explícitamente', $html);
        $this->assertStringContainsString('Receptor sugerido (requiere confirmación)', $html);
        $this->assertDoesNotMatchRegularExpression('/<option[^>]*value="101"[^>]*selected/i', $html);
        $this->assertDatabaseCount('padron_revision_decisiones', 0);
    }
}
