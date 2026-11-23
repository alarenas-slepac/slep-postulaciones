<?php

namespace Tests\Feature;

use Illuminate\Database\MySqlConnection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\IsolatedSecurityTestCase;

class DotacionFinanciamientoExportacionMigrationTest extends IsolatedSecurityTestCase
{
    private const TABLE = 'dotacion_financiamiento_exports';
    private const FILE = '2026_11_23_200000_create_dotacion_financiamiento_exports.php';

    public function test_repara_el_indice_faltante_y_conserva_una_solicitud(): void
    {
        $migration = require database_path('migrations/'.self::FILE);
        $migration->up();
        Schema::table(self::TABLE, fn (Blueprint $table) => $table->dropIndex('dfe_expires_idx'));
        $id = '00000000-0000-4000-8000-000000000001';
        DB::table(self::TABLE)->insert([
            'id' => $id, 'user_id' => 1, 'anio' => 2027,
            'filtros' => '{"q":"","comuna":""}', 'estado' => 'pendiente', 'expires_at' => now()->addHour(),
        ]);
        $migration->up();
        $migration->up();
        $this->assertTrue(Schema::hasIndex(self::TABLE, 'dfe_user_created_idx'));
        $this->assertTrue(Schema::hasIndex(self::TABLE, 'dfe_expires_idx'));
        $this->assertSame($id, DB::table(self::TABLE)->sole()->id);
    }

    public function test_compila_ddl_mysql_con_identificadores_cortos_y_pk_en_el_create(): void
    {
        $connection = new MySqlConnection(null, 'sga_test', 'sga_', ['driver' => 'mysql', 'prefix_indexes' => true]);
        $connection->useDefaultSchemaGrammar();
        $sql = [];
        $compile = function ($name, $definition, bool $create) use ($connection, &$sql): void {
            $blueprint = new Blueprint($connection, $name);
            if ($create) {
                $blueprint->create();
            }
            $definition($blueprint);
            array_push($sql, ...$blueprint->toSql());
        };
        Schema::shouldReceive('hasTable')->with(self::TABLE)->once()->andReturn(false);
        Schema::shouldReceive('hasIndex')->with(self::TABLE, 'dfe_user_created_idx')->once()->andReturn(false);
        Schema::shouldReceive('hasIndex')->with(self::TABLE, 'dfe_expires_idx')->once()->andReturn(false);
        Schema::shouldReceive('create')->once()->andReturnUsing(fn ($name, $definition) => $compile($name, $definition, true));
        Schema::shouldReceive('table')->twice()->andReturnUsing(fn ($name, $definition) => $compile($name, $definition, false));
        (require database_path('migrations/'.self::FILE))->up();

        $this->assertCount(3, $sql);
        $this->assertStringContainsString('primary key ('."\x60".'id'."\x60".')', $sql[0]);
        $this->assertStringContainsString('dfe_user_created_idx', $sql[1]);
        $this->assertStringContainsString('dfe_expires_idx', $sql[2]);
        preg_match_all('/\x60([^\x60]+)\x60/', implode(';', $sql), $identificadores);
        foreach ($identificadores[1] as $identificador) {
            $this->assertLessThanOrEqual(64, strlen($identificador));
        }
    }
}
