<?php

namespace Tests\Feature;

use Illuminate\Database\MySqlConnection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\Support\IsolatedSecurityTestCase;

class DescuentoCgrObservacionesMigrationTest extends IsolatedSecurityTestCase
{
    private const TABLA = 'descuentos_cgr_observaciones';

    public function test_mysql_compila_las_claves_y_reanuda_una_migracion_parcial(): void
    {
        $conexion = new MySqlConnection(null, 'sga_pruebas', 'sga_', ['driver' => 'mysql', 'prefix_indexes' => true]);
        $conexion->useDefaultSchemaGrammar();
        $sql = [];
        $compilar = function (string $nombre, callable $definicion, bool $creando = false) use ($conexion, &$sql): void {
            $plano = new Blueprint($conexion, $nombre);
            if ($creando) {
                $plano->create();
            }
            $definicion($plano);
            array_push($sql, ...$plano->toSql());
        };
        Schema::shouldReceive('getConnection')->twice()->andReturn($conexion);
        Schema::shouldReceive('hasTable')->with(self::TABLA)->twice()->andReturn(false, true);
        Schema::shouldReceive('hasIndex')->with(self::TABLA, 'cgr_obs_pend_idx')->twice()->andReturn(false, true);
        Schema::shouldReceive('getForeignKeys')->with(self::TABLA)->twice()->andReturn([], [
            ['name' => 'cgr_obs_desc_fk'],
            ['name' => 'cgr_obs_autor_fk'],
        ]);
        Schema::shouldReceive('create')->once()->andReturnUsing(fn ($nombre, $definicion) => $compilar($nombre, $definicion, true));
        Schema::shouldReceive('table')->times(5)->andReturnUsing($compilar);
        $migracion = require database_path('migrations/2026_11_26_120000_create_descuentos_cgr_observaciones_table.php');

        $migracion->up();
        $this->assertCount(5, $sql);
        $this->assertStringContainsString('cgr_obs_pend_idx', $sql[1]);
        $this->assertStringContainsString('cgr_obs_desc_fk', $sql[2]);
        $this->assertStringContainsString('cgr_obs_autor_fk', $sql[3]);
        $this->assertStringContainsString('cgr_obs_reenvio_fk', $sql[4]);
        preg_match_all('/\x60([^\x60]+)\x60/', implode(' ', $sql), $identificadores);
        foreach ($identificadores[1] as $identificador) {
            $this->assertLessThanOrEqual(64, strlen($identificador));
        }

        $pendiente = $sql[4];
        $sql = [];
        $migracion->up();
        $this->assertSame([$pendiente], $sql);
    }
}
