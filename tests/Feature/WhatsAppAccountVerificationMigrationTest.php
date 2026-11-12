<?php

namespace Tests\Feature;

use Illuminate\Database\MySqlConnection;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\IsolatedSecurityTestCase;

class WhatsAppAccountVerificationMigrationTest extends IsolatedSecurityTestCase
{
    private const TABLE = 'account_whatsapp_verifications';
    private const FILE = '2026_11_12_160000_create_account_whatsapp_verifications.php';

    public static function partials(): array
    {
        return ['nueva' => [false, false, false], 'tabla sin restricciones' => [true, false, false],
            'indice usuario confirmado' => [true, true, false], 'indice telefono confirmado' => [true, false, true],
            'indices confirmados' => [true, true, true]];
    }

    #[DataProvider('partials')]
    public function test_recupera_ddl_parcial_y_reversion_no_elimina_confirmaciones(bool $exists, bool $userIndex, bool $phoneIndex): void
    {
        $migration = require database_path('migrations/'.self::FILE);
        $migration->up();
        $user = $this->testUser();
        DB::table(self::TABLE)->insert(['user_id' => $user->id, 'verified_phone' => '+56900000001', 'verified_at' => now()]);
        if ($exists) {
            Schema::table(self::TABLE, function (Blueprint $t) use ($userIndex, $phoneIndex): void {
                $t->dropForeign(['user_id']);
                if (! $userIndex) { $t->dropUnique('awv_user_uq'); }
                if (! $phoneIndex) { $t->dropUnique('awv_phone_uq'); }
            });
        }
        $before = (array) DB::table(self::TABLE)->sole();
        $migration->up(); $migration->up(); $migration->down();
        $this->assertTrue(Schema::hasIndex(self::TABLE, 'awv_user_uq'));
        $this->assertTrue(Schema::hasIndex(self::TABLE, 'awv_phone_uq'));
        $this->assertCount(1, Schema::getForeignKeys(self::TABLE));
        $this->assertSame($before, (array) DB::table(self::TABLE)->sole());
    }

    #[DataProvider('partials')]
    public function test_sql_mysql_compila_restricciones_explicitas_cortas_y_solo_repara_lo_pendiente(bool $exists, bool $userIndex, bool $phoneIndex): void
    {
        $connection = new MySqlConnection(null, 'sga_synthetic_test', 'sga_', ['driver' => 'mysql']);
        $connection->useDefaultSchemaGrammar(); $sql = [];
        Schema::clearResolvedInstance('db.schema'); $this->app->forgetInstance('db.schema');
        Schema::shouldReceive('hasTable')->once()->with(self::TABLE)->andReturn($exists);
        Schema::shouldReceive('hasIndex')->once()->with(self::TABLE, 'awv_user_uq')->andReturn($userIndex);
        Schema::shouldReceive('hasIndex')->once()->with(self::TABLE, 'awv_phone_uq')->andReturn($phoneIndex);
        Schema::shouldReceive('getForeignKeys')->once()->with(self::TABLE)->andReturn([]);
        $compile = function ($name, $definition, bool $create = false) use ($connection, &$sql): void {
            $blueprint = new Blueprint($connection, $name);
            if ($create) { $blueprint->create(); }
            $definition($blueprint); array_push($sql, ...$blueprint->toSql());
        };
        if (! $exists) { Schema::shouldReceive('create')->once()->with(self::TABLE, \Mockery::type(\Closure::class))->andReturnUsing(fn ($n, $d) => $compile($n, $d, true)); }
        Schema::shouldReceive('table')->times((int) ! $userIndex + (int) ! $phoneIndex + 1)
            ->with(self::TABLE, \Mockery::type(\Closure::class))->andReturnUsing($compile);
        (require database_path('migrations/'.self::FILE))->up(); \Mockery::close();
        $joined = implode(';', $sql);
        $this->assertStringNotContainsString('drop ', strtolower($joined));
        $this->assertStringContainsString('`awv_user_fk`', $joined);
        if (! $userIndex) { $this->assertStringContainsString('`awv_user_uq`', $joined); }
        if (! $phoneIndex) { $this->assertStringContainsString('`awv_phone_uq`', $joined); }
        preg_match_all('/`([^`]+)`/', $joined, $matches);
        foreach ($matches[1] as $identifier) { $this->assertLessThanOrEqual(64, strlen($identifier), $identifier); }
    }

    public function test_indice_unico_impide_confirmar_mismo_telefono_en_dos_cuentas(): void
    {
        (require database_path('migrations/'.self::FILE))->up();
        $first = $this->testUser(1); $second = $this->testUser(2);
        DB::table(self::TABLE)->insert(['user_id' => $first->id, 'verified_phone' => '+56900000001', 'verified_at' => now()]);
        $this->expectException(QueryException::class);
        DB::table(self::TABLE)->insert(['user_id' => $second->id, 'verified_phone' => '+56900000001', 'verified_at' => now()]);
    }
}
