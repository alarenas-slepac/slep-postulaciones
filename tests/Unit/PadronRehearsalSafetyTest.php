<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Support\PadronRehearsal;

class PadronRehearsalSafetyTest extends TestCase
{
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
