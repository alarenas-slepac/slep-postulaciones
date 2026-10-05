<?php

namespace Tests\Unit;

use App\Support\DotacionLecturaCache;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class DotacionLecturaCacheTest extends TestCase
{
    public function test_cachea_nulos_y_comparte_el_ambito_anidado(): void
    {
        $calls = 0;
        $query = function () use (&$calls) { $calls++; return null; };
        DotacionLecturaCache::ejecutar(function () use ($query): void {
            $this->assertNull(DotacionLecturaCache::recordar('missing', $query));
            DotacionLecturaCache::ejecutar(fn () => DotacionLecturaCache::recordar('missing', $query));
        });
        $this->assertSame(1, $calls);
        DotacionLecturaCache::ejecutar(fn () => DotacionLecturaCache::recordar('missing', $query));
        $this->assertSame(2, $calls);
    }

    public function test_se_descarta_al_fallar_y_no_cachea_fuera_del_ambito(): void
    {
        try {
            DotacionLecturaCache::ejecutar(function (): void {
                DotacionLecturaCache::recordar('key', fn () => 'old');
                throw new RuntimeException('synthetic');
            });
        } catch (RuntimeException $e) {
            $this->assertSame('synthetic', $e->getMessage());
        }
        $this->assertSame('new', DotacionLecturaCache::recordar('key', fn () => 'new'));
        $this->assertSame('latest', DotacionLecturaCache::recordar('key', fn () => 'latest'));
        $this->assertSame('next', DotacionLecturaCache::ejecutar(fn () => DotacionLecturaCache::recordar('key', fn () => 'next')));
    }
}
