<?php

namespace Tests\Feature;

use App\Http\Controllers\Gestion\EstadisticasController;
use App\Models\SolicitudReemplazo;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PadronEstadisticasHistoricasTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        Schema::create('establecimientos', function (Blueprint $t) {
            $t->id(); $t->integer('rbd'); $t->string('nombre_establecimiento');
        });
        Schema::create('reemplazos_personal', function (Blueprint $t) {
            $t->id(); $t->string('rut'); $t->string('nombre');
        });
        Schema::create('solicitudes_reemplazo', function (Blueprint $t) {
            $t->id(); $t->integer('establecimiento_id'); $t->integer('reemplazo_personal_id')->nullable();
            $t->string('estado'); $t->string('contacto_nombre')->nullable(); $t->string('contacto_email')->nullable();
            foreach (['fecha_inicio', 'fecha_termino', 'fecha_inicio_trabajo'] as $field) {
                $t->date($field)->nullable();
            }
            $t->json('padron_personal_snapshot')->nullable();
        });
        DB::table('establecimientos')->insert([
            ['id' => 1, 'rbd' => 99999, 'nombre_establecimiento' => 'Escuela sintética A'],
            ['id' => 2, 'rbd' => 99998, 'nombre_establecimiento' => 'Escuela sintética B'],
        ]);
        DB::table('reemplazos_personal')->insert(['id' => 101, 'rut' => '111111111', 'nombre' => 'Nombre actual sintético']);
    }

    private function solicitud(int $id, int $est, ?string $nombre = null, int $personal = 101): void
    {
        $data = ['id' => $id, 'establecimiento_id' => $est, 'reemplazo_personal_id' => $personal, 'estado' => 'cerrado'];
        if ($nombre !== null) {
            $data['padron_personal_snapshot'] = json_encode(['version' => 1,
                'personal' => ['id' => $personal, 'rut' => '111111111', 'nombre' => $nombre]], JSON_THROW_ON_ERROR);
        }
        DB::table('solicitudes_reemplazo')->insert($data);
    }

    private function datos(?int $est = 1): array
    {
        return app(EstadisticasController::class)->index(Request::create('/estadisticas', 'GET',
            $est === null ? [] : ['establecimiento_id' => $est]))->getData();
    }

    public function test_ranking_uses_last_available_snapshot_within_filter_without_changing_counts(): void
    {
        $this->solicitud(1, 1, 'Primera copia sintética');
        $this->solicitud(2, 1, 'Última copia sintética');
        $this->solicitud(3, 1); // Un documento sin copia no reemplaza una identidad histórica disponible.
        $this->solicitud(4, 2, 'Copia de otro establecimiento');
        $datos = $this->datos();
        $this->assertSame(3, $datos['totalSolicitudes']);
        $this->assertSame('Última copia sintética', $datos['rankingRows'][0]['nombre']);
        $this->assertSame(3, $datos['rankingRows'][0]['total']);
        $this->assertSame(['Última copia sintética'], $datos['rankingChart']['labels']);
        $this->assertSame('Copia de otro establecimiento', $this->datos(2)['rankingRows'][0]['nombre']);
        $this->assertSame('establecimientos', $this->datos(null)['rankingMode']);
        $this->assertSame(4, $this->datos(null)['totalSolicitudes']);
    }

    public function test_legacy_fallback_works_with_null_snapshot_and_before_migration(): void
    {
        $this->solicitud(1, 1);
        $this->assertSame('Nombre actual sintético', $this->datos()['rankingRows'][0]['nombre']);
        Schema::table('solicitudes_reemplazo', fn (Blueprint $t) => $t->dropColumn('padron_personal_snapshot'));
        $this->assertSame('Nombre actual sintético', $this->datos()['rankingRows'][0]['nombre']);
    }

    public function test_ranking_preserves_ids_and_handles_missing_current_record(): void
    {
        $this->solicitud(1, 1, 'Identidad sintética', 101);
        $this->solicitud(2, 1, 'Identidad sintética', 999); // Copia disponible, sin fila actual.
        $this->solicitud(3, 1, null, 998);
        $rows = $this->datos()['rankingRows'];
        $this->assertCount(3, $rows);
        $this->assertSame(2, $rows->where('nombre', 'Identidad sintética')->count());
        $this->assertSame('ID 998', $rows->firstWhere('nombre', 'Funcionario sin nombre')['detalle']);
    }

    public function test_invalid_snapshot_does_not_silently_use_current_identity(): void
    {
        $this->solicitud(1, 1, 'Copia sintética');
        DB::table('solicitudes_reemplazo')->where('id', 1)->update([
            'padron_personal_snapshot' => json_encode(['version' => 1, 'personal' => ['id' => 999]]),
        ]);
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $this->datos();
    }
}
