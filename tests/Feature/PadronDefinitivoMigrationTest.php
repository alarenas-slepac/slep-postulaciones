<?php

namespace Tests\Feature;

use Illuminate\Database\MySqlConnection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\IsolatedSecurityTestCase;

class PadronDefinitivoMigrationTest extends IsolatedSecurityTestCase
{
    private const TABLE = 'votacion_padrones_definitivos';
    private const COLUMNS = ['jornada_votacion_id', 'publicado_at'];
    private const CREATE = '2026_11_09_230000_create_votacion_padrones_definitivos.php';
    private const REPAIR = '2026_11_09_231000_ensure_padrones_definitivos_publicacion_index.php';

    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('jornadas_votacion', fn (Blueprint $table) => $table->id());
        DB::table('jornadas_votacion')->insert(['id' => 1]);
    }

    public function test_instalacion_nueva_crea_indice_y_fk_sin_duplicarlos_al_reintentar(): void
    {
        $migration = require database_path('migrations/'.self::CREATE);
        $migration->up();
        $migration->up();
        (require database_path('migrations/'.self::REPAIR))->up();

        $this->assertPublicationIndex();
        $foreignKeys = Schema::getForeignKeys(self::TABLE);
        $this->assertCount(1, $foreignKeys);
        $this->assertSame(['jornada_votacion_id'], $foreignKeys[0]['columns']);
        $this->assertSame('jornadas_votacion', $foreignKeys[0]['foreign_table']);
        $this->assertSame('restrict', strtolower($foreignKeys[0]['on_delete']));
    }

    public function test_reintento_completa_indice_tras_fallo_parcial_y_conserva_versiones_y_fk(): void
    {
        $before = $this->createPartialTable();
        $foreignKeys = Schema::getForeignKeys(self::TABLE);
        $migration = require database_path('migrations/'.self::CREATE);
        $migration->up();
        $migration->up();

        $this->assertPublicationIndex();
        $this->assertEquals($before, DB::table(self::TABLE)->first());
        $this->assertSame($foreignKeys, Schema::getForeignKeys(self::TABLE));
        $migration->down();
        $this->assertEquals($before, DB::table(self::TABLE)->first());
    }

    public function test_migracion_auxiliar_repara_si_la_original_ya_fue_registrada_como_aplicada(): void
    {
        $before = $this->createPartialTable();
        $migration = require database_path('migrations/'.self::REPAIR);
        $migration->up();
        $migration->up();
        $migration->down();

        $this->assertPublicationIndex();
        $this->assertEquals($before, DB::table(self::TABLE)->first());
    }

    public function test_conserva_indice_equivalente_preexistente_sin_agregar_otro(): void
    {
        $before = $this->createPartialTable();
        Schema::table(self::TABLE, fn (Blueprint $table) => $table->index(self::COLUMNS, 'vpd_indice_previo'));
        (require database_path('migrations/'.self::CREATE))->up();
        (require database_path('migrations/'.self::REPAIR))->up();

        $this->assertTrue(Schema::hasIndex(self::TABLE, 'vpd_indice_previo'));
        $this->assertFalse(Schema::hasIndex(self::TABLE, 'vpd_jornada_publicado_idx'));
        $this->assertCount(1, $this->publicationIndexes());
        $this->assertEquals($before, DB::table(self::TABLE)->first());
    }

    public function test_migracion_auxiliar_no_crea_tablas_ajenas_si_el_modulo_no_esta_instalado(): void
    {
        (require database_path('migrations/'.self::REPAIR))->up();
        $this->assertFalse(Schema::hasTable(self::TABLE));
    }

    public function test_sql_mysql_de_instalacion_usa_nombres_explicitos_dentro_del_limite(): void
    {
        $sql = $this->compileMySql(self::CREATE, false);

        $this->assertStringContainsString('constraint `vpd_jornada_fk`', $sql);
        $this->assertStringContainsString('add index `vpd_jornada_publicado_idx`', $sql);
        $this->assertStringContainsString('on delete restrict', $sql);
        $this->assertMySqlIdentifiers($sql);
    }

    public function test_sql_mysql_de_recuperacion_original_y_auxiliar_respeta_limite(): void
    {
        foreach ([self::CREATE, self::REPAIR] as $file) {
            $sql = $this->compileMySql($file, true);

            $this->assertStringNotContainsString('create table', $sql);
            $this->assertStringNotContainsString('drop ', $sql);
            $this->assertStringContainsString('add index `vpd_jornada_publicado_idx`', $sql);
            $this->assertMySqlIdentifiers($sql);
        }
    }

    private function createPartialTable(): object
    {
        // Estado que deja MySQL: CREATE y FK ya aplicados, índice compuesto pendiente.
        Schema::create(self::TABLE, function (Blueprint $table): void {
            $table->id();
            $table->foreignId('jornada_votacion_id')->constrained('jornadas_votacion')->restrictOnDelete();
            $table->unsignedInteger('personas');
            $table->json('datos');
            $table->json('resumen');
            $table->string('base_huella', 64);
            $table->string('certificado_path')->nullable();
            $table->unsignedBigInteger('creado_por');
            $table->unsignedBigInteger('publicado_por')->nullable();
            $table->timestamp('publicado_at')->nullable();
            $table->timestamps();
        });
        DB::table(self::TABLE)->insert([
            'jornada_votacion_id' => 1, 'personas' => 0, 'datos' => '{}', 'resumen' => '{}',
            'base_huella' => hash('sha256', 'version-sintetica'), 'creado_por' => 1,
            'publicado_at' => '2026-11-09 18:00:00',
        ]);

        return DB::table(self::TABLE)->first();
    }

    private function publicationIndexes(): array
    {
        return array_values(array_filter(Schema::getIndexes(self::TABLE),
            fn (array $index) => $index['columns'] === self::COLUMNS));
    }

    private function assertPublicationIndex(): void
    {
        $this->assertTrue(Schema::hasIndex(self::TABLE, 'vpd_jornada_publicado_idx'));
        $this->assertCount(1, $this->publicationIndexes());
    }

    private function compileMySql(string $file, bool $exists): string
    {
        $connection = new MySqlConnection(null, 'sga_synthetic_test', '', ['driver' => 'mysql']);
        $connection->useDefaultSchemaGrammar();
        $statements = [];
        // Compilar el SQL real de las migraciones sin conectar con una BD MySQL.
        Schema::clearResolvedInstance('db.schema');
        $this->app->forgetInstance('db.schema');
        Schema::shouldReceive('hasTable')->once()->with(self::TABLE)->andReturn($exists);
        Schema::shouldReceive('hasIndex')->once()->with(self::TABLE, self::COLUMNS)->andReturn(! $exists);
        $method = $exists ? 'table' : 'create';
        Schema::shouldReceive($method)->once()->with(self::TABLE, \Mockery::type(\Closure::class))
            ->andReturnUsing(function (string $table, \Closure $definition) use ($connection, $exists, &$statements): void {
                $blueprint = new Blueprint($connection, $table);
                if (! $exists) {
                    $blueprint->create();
                }
                $definition($blueprint);
                $statements = $blueprint->toSql();
            });
        (require database_path('migrations/'.$file))->up();
        \Mockery::close();

        return implode(";\n", $statements);
    }

    private function assertMySqlIdentifiers(string $sql): void
    {
        preg_match_all('/`([^`]+)`/', $sql, $matches);
        $this->assertNotEmpty($matches[1]);
        foreach ($matches[1] as $identifier) {
            $this->assertLessThanOrEqual(64, strlen($identifier), $identifier);
        }
    }
}
