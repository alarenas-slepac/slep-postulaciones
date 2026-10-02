<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\IsolatedSecurityTestCase;

class SolicitudReemplazoFechaInicioTrabajoExportTest extends IsolatedSecurityTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('modules', function (Blueprint $table): void {
            $table->id(); $table->string('key');
        });
        DB::table('modules')->insert(['key' => 'gestion.solicitudes-reemplazo']);
        Schema::create('solicitudes_reemplazo', function (Blueprint $table): void {
            $table->id(); $table->string('numero_solicitud'); $table->string('estado');
            foreach (['establecimiento_id', 'reemplazo_personal_id', 'postulant_profile_id',
                'contrato_trabajo_postulant_profile_id', 'area_desempeno_id', 'derivada_a_user_id',
                'uatp_decision_user_id', 'plani_decision_user_id'] as $field) {
                $table->unsignedBigInteger($field)->nullable();
            }
            $table->date('fecha_inicio'); $table->date('fecha_inicio_trabajo')->nullable();
            $table->date('fecha_termino'); $table->timestamp('uatp_decision_at')->nullable();
            $table->text('observaciones')->nullable(); $table->timestamps();
        });
        Schema::create('solicitud_reemplazo_jornadas', function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('solicitud_reemplazo_id');
        });
    }

    public static function exportScopes(): array
    {
        return [
            'GDP' => ['gdp', 'aceptada', 'o'],
            'UATP' => ['uatp', 'pendiente_uatp', 'p'],
            'Validación' => ['validacion', 'pendiente_validacion', 'v'],
        ];
    }

    #[DataProvider('exportScopes')]
    public function test_descarga_exporta_inicio_trabajo_y_respeta_nulos_alineacion_y_filtros(string $scope, string $state, string $prefix): void
    {
        foreach ([1 => '2026-10-02', 2 => null, 3 => '2026-10-03'] as $id => $start) {
            DB::table('solicitudes_reemplazo')->insert([
                'id' => $id, 'numero_solicitud' => ($id === 3 ? 'AJENA-' : 'SINTETICA-').$id,
                'estado' => $state, 'fecha_inicio' => '2026-09-28', 'fecha_inicio_trabajo' => $start,
                'fecha_termino' => '2026-10-31', 'created_at' => '2026-09-27 12:00:00',
                'observaciones' => 'Observación sintética '.$id,
            ]);
        }
        $this->actingAs($this->testUser(1, 3))->withSession(['active_role' => 'admin']);
        $this->withoutExceptionHandling();
        $response = $this->get(route('gestion.solicitudes-reemplazo.exportar', [
            'scope' => $scope, $prefix.'_numero' => 'SINTETICA',
        ]))->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $content = $response->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $content);
        $stream = fopen('php://temp', 'w+');
        try {
            fwrite($stream, substr($content, 3));
            rewind($stream);
            $headers = fgetcsv($stream, 0, ';');
            $this->assertSame(['Fecha inicio', 'Fecha Inicio Trabajo', 'Fecha termino'], array_slice($headers, 3, 3));
            $this->assertCount($scope === 'gdp' ? 36 : 30, $headers);
            $rows = [];
            while (($values = fgetcsv($stream, 0, ';')) !== false) {
                $this->assertCount(count($headers), $values);
                $row = array_combine($headers, $values);
                $rows[$row['N solicitud']] = $row;
            }
            $this->assertCount(2, $rows);
            $this->assertSame('02-10-2026', $rows['SINTETICA-1']['Fecha Inicio Trabajo']);
            $this->assertSame('', $rows['SINTETICA-2']['Fecha Inicio Trabajo']);
            foreach ([1, 2] as $id) {
                $this->assertSame('28-09-2026', $rows['SINTETICA-'.$id]['Fecha inicio']);
                $this->assertSame('31-10-2026', $rows['SINTETICA-'.$id]['Fecha termino']);
                $this->assertSame('Observación sintética '.$id, $rows['SINTETICA-'.$id]['Observaciones']);
            }
        } finally {
            fclose($stream);
        }
    }
}
