<?php

namespace Tests\Unit;

use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;
use Tests\Support\PadronMySqlLab;

class PadronSqlLabTest extends TestCase
{
    private function connection(string $version, string $isolationVariable): PDO
    {
        $pdo = $this->createMock(PDO::class);
        $pdo->method('query')->willReturnCallback(function (string $sql) use ($version, $isolationVariable) {
            $statement = $this->createMock(PDOStatement::class);
            $statement->method('fetchColumn')->willReturn(match ($sql) {
                'SELECT VERSION()' => $version,
                'SELECT @@'.$isolationVariable => 'REPEATABLE-READ',
                'SELECT @@default_storage_engine' => 'InnoDB',
                'SELECT @@innodb_lock_wait_timeout' => 50,
                default => throw new \RuntimeException('Consulta no esperada: '.$sql),
            });
            return $statement;
        });
        return $pdo;
    }

    public function test_mariadb_uses_its_own_isolation_variable_and_lock_observer(): void
    {
        $pdo = $this->connection('10.11.18-MariaDB', 'tx_isolation');
        $server = PadronMySqlLab::server($pdo);
        $this->assertSame('MariaDB', $server['family']);
        $this->assertSame('10.11.18-MariaDB', $server['version']);
        $this->assertSame('information_schema.INNODB_LOCK_WAITS', $server['lock_observer']);
        $this->assertStringContainsString('trx_mysql_thread_id = ?', PadronMySqlLab::lockWaitSql($pdo));
        $this->assertStringNotContainsString('performance_schema', PadronMySqlLab::lockWaitSql($pdo));
    }

    public function test_mysql_eight_keeps_its_existing_observer(): void
    {
        $pdo = $this->connection('8.4.3', 'transaction_isolation');
        $this->assertSame('MySQL', PadronMySqlLab::server($pdo)['family']);
        $this->assertStringContainsString('performance_schema.data_lock_waits', PadronMySqlLab::lockWaitSql($pdo));
    }

    public function test_unknown_engine_version_is_not_silently_accepted(): void
    {
        $this->expectException(\RuntimeException::class);
        PadronMySqlLab::server($this->connection('5.7.44', 'transaction_isolation'));
    }

    public function test_other_mariadb_series_is_not_silently_certified(): void
    {
        $this->expectException(\RuntimeException::class);
        PadronMySqlLab::server($this->connection('11.4.8-MariaDB', 'tx_isolation'));
    }
}
