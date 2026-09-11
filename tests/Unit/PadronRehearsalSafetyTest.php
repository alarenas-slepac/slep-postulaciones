<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Support\PadronRehearsal;

class PadronRehearsalSafetyTest extends TestCase
{
    public function test_collects_both_release_types_without_counting_unconfirmed_scopes(): void
    {
        $hash = str_repeat('a', 64);
        $result = PadronRehearsal::expectedReleases([
            'bajas_asignaciones' => [self::confirmation(10, [501 => $hash]), ['confirmada' => false]],
            'traslados_asignaciones' => [self::confirmation(11, [502 => $hash, 503 => $hash], 9),
                ['confirmada' => false, 'alcance' => ['asignaciones' => [501 => $hash]]]],
        ]);
        $this->assertCount(3, $result);
        $this->assertSame(['hash' => $hash, 'confirmation' => 10, 'kind' => 'baja', 'origin' => null], $result[501]);
        $this->assertSame(['hash' => $hash, 'confirmation' => 11, 'kind' => 'traslado', 'origin' => 9], $result[502]);
        $this->assertSame($result[502], $result[503]);
        $this->assertSame([], PadronRehearsal::expectedReleases([]));
    }

    #[DataProvider('invalidReleases')]
    public function test_rejects_incomplete_or_overlapping_release_expectations(array $conflicts, string $code): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage($code);
        PadronRehearsal::expectedReleases($conflicts);
    }

    public static function invalidReleases(): array
    {
        $hash = str_repeat('a', 64);
        $baja = self::confirmation(1, [501 => $hash]);
        return [
            'baja and traslado overlap' => [['bajas_asignaciones' => [$baja],
                'traslados_asignaciones' => [self::confirmation(2, [501 => $hash], 1)]], 'overlapping_release_scope'],
            'two transfers overlap' => [['traslados_asignaciones' => [self::confirmation(2, [501 => $hash], 1),
                self::confirmation(3, [501 => $hash], 1)]], 'overlapping_release_scope'],
            'missing confirmation' => [['bajas_asignaciones' => [self::confirmation(0, [501 => $hash])]], 'invalid_release_confirmation'],
            'missing scope' => [['bajas_asignaciones' => [['confirmada' => true, 'ultima_id' => 1]]], 'invalid_release_confirmation'],
            'missing origin' => [['traslados_asignaciones' => [$baja]], 'invalid_transfer_origin'],
            'invalid id' => [['bajas_asignaciones' => [self::confirmation(1, [0 => $hash])]], 'invalid_release_scope'],
            'invalid hash' => [['bajas_asignaciones' => [self::confirmation(1, [501 => 'invalid'])]], 'invalid_release_scope'],
        ];
    }

    private static function confirmation(int $id, array $assignments, ?int $origin = null): array
    {
        return ['confirmada' => true, 'ultima_id' => $id, 'alcance' => ['asignaciones' => $assignments, 'origen' => $origin]];
    }

    #[DataProvider('invalidRoots')]
    public function test_rejects_paths_outside_the_private_lab(string $root): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('private_root_required');
        PadronRehearsal::privateRoot($root);
    }

    public static function invalidRoots(): array
    {
        return [
            'empty' => [''],
            'workspace' => [dirname(__DIR__, 2)],
            'nonexistent' => [dirname(__DIR__).'/no-such-rehearsal-root'],
            'lab parent' => [getenv('LOCALAPPDATA').'/SlepPadronLab'],
            'traversal' => [getenv('LOCALAPPDATA').'/SlepPadronLab/..'],
        ];
    }
}
