<?php

namespace Tests\Feature;

use App\Models\CometidoFuncionario;
use App\Models\IncumplimientoLaboral;
use App\Models\ReemplazoPersonal;
use App\Models\ReemplazoPersonalHistorico;
use App\Models\SolicitudReemplazo;
use App\Services\Padron\PadronHistorialService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PadronHistorialTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        Schema::create('reemplazos_personal', function (Blueprint $t) {
            $t->id(); $t->integer('establecimiento_id'); $t->integer('rbd');
            $t->string('rut'); $t->string('nombre'); $t->string('estatuto');
            $t->string('tipocontrato'); $t->integer('jornada');
            $t->integer('anio'); $t->integer('mes'); $t->date('fecha_antiguedad')->nullable();
            $t->timestamps();
        });
        foreach (PadronHistorialService::DOCUMENTOS as $table) {
            Schema::create($table, function (Blueprint $t) {
                $t->id(); $t->foreignId('reemplazo_personal_id')->nullable()->constrained('reemplazos_personal');
                $t->integer('establecimiento_id')->nullable(); $t->string('estado')->nullable(); $t->timestamps();
            });
        }
        $this->migration()->up();
        DB::table('reemplazos_personal')->insert([
            ['id' => 101, 'establecimiento_id' => 1, 'rbd' => 99999, 'rut' => '111111111',
                'nombre' => 'Docente sintético original', 'estatuto' => 'DOCENTE', 'tipocontrato' => 'CONTRATA',
                'jornada' => 44, 'anio' => 2026, 'mes' => 8, 'fecha_antiguedad' => '2010-01-01'],
            ['id' => 102, 'establecimiento_id' => 2, 'rbd' => 99998, 'rut' => '222222222',
                'nombre' => 'Asistente sintético', 'estatuto' => 'ASISTENTE', 'tipocontrato' => 'TITULAR',
                'jornada' => 40, 'anio' => 2026, 'mes' => 8, 'fecha_antiguedad' => '2015-01-01'],
        ]);
    }

    private function migration()
    {
        return require base_path('database/migrations/2026_09_08_150000_add_padron_snapshot_to_documentos.php');
    }

    private function changeCurrent(): void
    {
        DB::table('reemplazos_personal')->where('id', 101)->update([
            'nombre' => 'Nombre actualizado sintético', 'jornada' => 30, 'establecimiento_id' => 2,
            'rbd' => 99998, 'tipocontrato' => 'TITULAR', 'estatuto' => 'ASISTENTE', 'mes' => 9,
        ]);
    }

    public function test_new_documents_capture_contract_and_read_it_after_roster_changes(): void
    {
        foreach ([SolicitudReemplazo::class => 'funcionarioTitular', CometidoFuncionario::class => 'funcionarioPadron', IncumplimientoLaboral::class => 'reemplazoPersonal'] as $class => $relation) {
            $document = $class::create(['reemplazo_personal_id' => 101, 'establecimiento_id' => 1]);
            $this->assertSame('creacion_documento', $document->padron_personal_snapshot['origen']);
            $this->assertSame(101, $document->padron_personal_snapshot['personal']['id']);
        }
        $this->changeCurrent();
        foreach ([SolicitudReemplazo::class => 'funcionarioTitular', CometidoFuncionario::class => 'funcionarioPadron', IncumplimientoLaboral::class => 'reemplazoPersonal'] as $class => $relation) {
            foreach ([$class::first(), $class::with($relation)->first(), $class::select('id', 'reemplazo_personal_id')->with($relation.':id,nombre,estatuto')->first()] as $document) {
                $this->assertInstanceOf(ReemplazoPersonalHistorico::class, $document->$relation);
                $this->assertSame('Docente sintético original', $document->$relation->nombre);
                $this->assertSame(44, $document->$relation->jornada);
                $this->assertSame('DOCENTE', $document->$relation->estatuto);
                $this->assertSame(1, $document->$relation->establecimiento_id);
                $this->assertSame('2010-01-01', $document->$relation->fecha_antiguedad->format('Y-m-d'));
                $this->assertArrayNotHasKey('padron_personal_snapshot', $document->toArray());
            }
        }
        $this->assertSame(30, ReemplazoPersonal::findOrFail(101)->jornada);
        $this->assertDatabaseCount('reemplazos_personal', 2);
    }

    public function test_legacy_documents_are_frozen_before_update_without_changing_fk_or_timestamps(): void
    {
        foreach (PadronHistorialService::DOCUMENTOS as $table) {
            DB::table($table)->insert(['id' => 1, 'reemplazo_personal_id' => 101, 'estado' => 'cerrado', 'updated_at' => '2026-08-01 12:00:00']);
        }
        $service = app(PadronHistorialService::class);
        DB::transaction(function () use ($service) {
            $this->assertSame(3, $service->congelarReferencias([101]));
            $this->changeCurrent();
            $this->assertSame(0, $service->congelarReferencias([101]));
        });
        foreach (PadronHistorialService::DOCUMENTOS as $table) {
            $row = DB::table($table)->first();
            $this->assertSame(101, $row->reemplazo_personal_id);
            $this->assertSame('cerrado', $row->estado);
            $this->assertSame('2026-08-01 12:00:00', $row->updated_at);
            $this->assertSame(44, json_decode($row->padron_personal_snapshot, true)['personal']['jornada']);
        }
        $this->assertSame(44, SolicitudReemplazo::first()->funcionarioTitular->jornada);
    }

    public function test_snapshot_and_personnel_update_roll_back_together(): void
    {
        DB::table('solicitudes_reemplazo')->insert(['id' => 1, 'reemplazo_personal_id' => 101]);
        try {
            DB::transaction(function () {
                app(PadronHistorialService::class)->congelarReferencias([101]);
                $this->changeCurrent();
                throw new \RuntimeException('Fallo sintético posterior.');
            });
        } catch (\RuntimeException $exception) {
            $this->assertSame('Fallo sintético posterior.', $exception->getMessage());
        }
        $this->assertNull(SolicitudReemplazo::first()->padron_personal_snapshot);
        $this->assertSame(44, ReemplazoPersonal::findOrFail(101)->jornada);
    }

    public function test_same_titular_edits_preserve_snapshot_and_change_of_titular_captures_new_id(): void
    {
        $document = SolicitudReemplazo::create(['reemplazo_personal_id' => 101]);
        $snapshot = $document->padron_personal_snapshot;
        $this->changeCurrent();
        $document->update(['estado' => 'cerrado']);
        $this->assertSame($snapshot, $document->fresh()->padron_personal_snapshot);
        $document->update(['reemplazo_personal_id' => 102]);
        $this->assertSame(102, $document->funcionarioTitular->id);
        $this->assertSame('Asistente sintético', $document->funcionarioTitular->nombre);
        $this->assertSame('edicion_documento', $document->padron_personal_snapshot['origen']);
        $this->assertSame($snapshot, $document->padron_personal_snapshot['anteriores'][0]);
    }

    public function test_historical_projection_cannot_save_or_delete_personnel(): void
    {
        $document = SolicitudReemplazo::create(['reemplazo_personal_id' => 101]);
        $this->changeCurrent();
        $historical = $document->funcionarioTitular;
        $this->assertSame(44, $historical->refresh()->jornada);
        $this->assertSame(44, $historical->fresh()->jornada);
        foreach (['save', 'delete'] as $method) {
            try {
                $historical->$method();
                $this->fail('La copia debe ser de solo lectura.');
            } catch (\LogicException $exception) {
                $this->assertStringContainsString('histórica', $exception->getMessage());
            }
        }
        $this->assertSame(30, ReemplazoPersonal::findOrFail(101)->jornada);
        $this->assertDatabaseCount('reemplazos_personal', 2);
    }

    public function test_legacy_relation_without_snapshot_remains_compatible(): void
    {
        DB::table('solicitudes_reemplazo')->insert(['id' => 1, 'reemplazo_personal_id' => 101]);
        $this->assertInstanceOf(ReemplazoPersonal::class, SolicitudReemplazo::first()->funcionarioTitular);
        $this->assertNotInstanceOf(ReemplazoPersonalHistorico::class, SolicitudReemplazo::first()->funcionarioTitular);
        $this->assertSame(101, SolicitudReemplazo::with('funcionarioTitular:id,nombre')->first()->funcionarioTitular->id);
    }

    public function test_does_not_accept_a_snapshot_for_another_person(): void
    {
        $snapshot = app(PadronHistorialService::class)->capturar(102, 'prueba');
        DB::table('solicitudes_reemplazo')->insert(['id' => 1, 'reemplazo_personal_id' => 101, 'padron_personal_snapshot' => json_encode($snapshot)]);
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        SolicitudReemplazo::first()->funcionarioTitular;
    }

    public function test_freeze_requires_transaction_and_migration(): void
    {
        try {
            app(PadronHistorialService::class)->congelarReferencias([101]);
            $this->fail('Debe exigir transacción.');
        } catch (\LogicException $exception) {
            $this->assertStringContainsString('transacción', $exception->getMessage());
        }
        // Esquema sintético sin la columna de protección.
        Schema::table('solicitudes_reemplazo', fn (Blueprint $t) => $t->dropColumn('padron_personal_snapshot'));
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        DB::transaction(fn () => app(PadronHistorialService::class)->congelarReferencias([101]));
    }

    public function test_ac_document_without_personal_reference_does_not_capture(): void
    {
        $document = CometidoFuncionario::create(['reemplazo_personal_id' => null]);
        $this->assertNull($document->padron_personal_snapshot);
        $this->assertNull($document->funcionarioPadron);
    }

    public function test_documents_for_same_id_keep_distinct_versions_when_eager_loaded(): void
    {
        $old = SolicitudReemplazo::create(['reemplazo_personal_id' => 101]);
        $this->changeCurrent();
        $new = SolicitudReemplazo::create(['reemplazo_personal_id' => 101]);
        $documents = SolicitudReemplazo::with('funcionarioTitular:id,rut,nombre')->orderBy('id')->get();
        $this->assertSame($old->id, $documents[0]->id);
        $this->assertSame($new->id, $documents[1]->id);
        $this->assertSame(44, $documents[0]->funcionarioTitular->jornada);
        $this->assertSame(30, $documents[1]->funcionarioTitular->jornada);
        $this->assertNotSame($documents[0]->funcionarioTitular, $documents[1]->funcionarioTitular);
        $this->assertSame(['id', 'rut', 'nombre'], array_keys($documents[0]->funcionarioTitular->toArray()));
        $this->assertSame('Docente sintético original', $documents[0]->toArray()['funcionario_titular']['nombre']);
        $this->assertSame('Nombre actualizado sintético', $documents[1]->toArray()['funcionario_titular']['nombre']);
    }

    public function test_clearing_reference_preserves_prior_copy_without_returning_a_titular(): void
    {
        $document = CometidoFuncionario::create(['reemplazo_personal_id' => 101]);
        $snapshot = $document->padron_personal_snapshot;
        $document->update(['reemplazo_personal_id' => null]);
        $this->assertNull($document->funcionarioPadron);
        $this->assertNull($document->fresh()->load('funcionarioPadron')->funcionarioPadron);
        $this->assertSame($snapshot, $document->padron_personal_snapshot['anteriores'][0]);
        $document->update(['estado' => 'cerrado']);
        $document->update(['reemplazo_personal_id' => 102]);
        $this->assertSame(102, $document->funcionarioPadron->id);
        $this->assertSame($snapshot, $document->padron_personal_snapshot['anteriores'][0]);
    }

    public function test_rendicion_reads_same_historical_category_with_or_without_eager_loading(): void
    {
        $document = CometidoFuncionario::create(['reemplazo_personal_id' => 101]);
        $this->changeCurrent();
        $controller = app(\App\Http\Controllers\Tramites\CometidoFuncionarioRendicionController::class);
        $method = new \ReflectionMethod($controller, 'textoCategoriaAaeeCometido');
        $lazy = $method->invoke($controller, $document->fresh());
        $eager = $method->invoke($controller, $document->fresh(['funcionarioPadron']));
        $this->assertSame('DOCENTE CONTRATA', $lazy);
        $this->assertSame($lazy, $eager);
        $this->assertSame('ASISTENTE', ReemplazoPersonal::findOrFail(101)->estatuto);
    }
}
