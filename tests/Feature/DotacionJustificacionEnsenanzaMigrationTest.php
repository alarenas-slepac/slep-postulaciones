<?php

namespace Tests\Feature;

use Illuminate\Database\MySqlConnection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\IsolatedSecurityTestCase;

class DotacionJustificacionEnsenanzaMigrationTest extends IsolatedSecurityTestCase
{
    private const TABLE = 'dotacion_sobredotacion_justificaciones';
    private const MIGRATION = '2026_11_11_140000_add_ensenanza_to_dotacion_justificaciones.php';

    public static function estadosParciales(): array
    {
        return ['sin columnas' => [false, false], 'nivel confirmado' => [true, false],
            'areas confirmadas' => [false, true], 'ambas confirmadas' => [true, true]];
    }

    #[DataProvider('estadosParciales')]
    public function test_reintento_de_ddl_y_reversion_conservan_los_antecedentes(bool $nivel, bool $areas): void
    {
        Schema::create(self::TABLE, function (Blueprint $t) use ($nivel, $areas): void {
            $t->id(); $t->text('justificacion');
            if ($nivel) { $t->string('nivel_ensenanza', 24)->nullable(); }
            if ($areas) { $t->json('areas_ensenanza')->nullable(); }
        });
        $anterior = ['id' => 1, 'justificacion' => 'Fundamento histórico sintético.'];
        if ($nivel) { $anterior['nivel_ensenanza'] = 'basica'; }
        if ($areas) { $anterior['areas_ensenanza'] = '[{"id":11,"nombre":"Área histórica"}]'; }
        DB::table(self::TABLE)->insert($anterior);
        $m = require database_path('migrations/'.self::MIGRATION);
        $m->up(); $m->up(); $m->down();
        $guardado = (array) DB::table(self::TABLE)->sole();
        $this->assertSame($anterior + ['nivel_ensenanza' => null, 'areas_ensenanza' => null], $guardado);
    }

    #[DataProvider('estadosParciales')]
    public function test_sql_mysql_solo_agrega_columnas_pendientes_anulables_y_usa_identificadores_cortos(bool $nivel, bool $areas): void
    {
        $connection = new MySqlConnection(null, 'sga_synthetic_test', '', ['driver' => 'mysql']);
        $connection->useDefaultSchemaGrammar();
        $statements = [];
        Schema::clearResolvedInstance('db.schema');
        $this->app->forgetInstance('db.schema');
        Schema::shouldReceive('hasTable')->once()->with(self::TABLE)->andReturn(true);
        Schema::shouldReceive('hasColumn')->once()->with(self::TABLE, 'nivel_ensenanza')->andReturn($nivel);
        Schema::shouldReceive('hasColumn')->once()->with(self::TABLE, 'areas_ensenanza')->andReturn($areas);
        Schema::shouldReceive('table')->times((int) ! $nivel + (int) ! $areas)
            ->with(self::TABLE, \Mockery::type(\Closure::class))
            ->andReturnUsing(function ($table, $definition) use ($connection, &$statements): void {
                $blueprint = new Blueprint($connection, $table);
                $definition($blueprint);
                array_push($statements, ...$blueprint->toSql());
            });
        (require database_path('migrations/'.self::MIGRATION))->up();
        \Mockery::close();
        $this->assertCount((int) ! $nivel + (int) ! $areas, $statements);
        $sql = implode(';', $statements);
        if (! $nivel) { $this->assertStringContainsString('add `nivel_ensenanza` varchar(24) null', $sql); }
        if (! $areas) { $this->assertStringContainsString('add `areas_ensenanza` json null', $sql); }
        $this->assertDoesNotMatchRegularExpression('/\b(drop|create|index|foreign|unique)\b/i', $sql);
        preg_match_all('/`([^`]+)`/', $sql, $matches);
        foreach ($matches[1] as $name) { $this->assertLessThanOrEqual(64, strlen($name)); }
    }

    public function test_tabla_ausente_no_se_crea_sin_su_esquema_original(): void
    {
        (require database_path('migrations/'.self::MIGRATION))->up();
        $this->assertFalse(Schema::hasTable(self::TABLE));
    }
}
