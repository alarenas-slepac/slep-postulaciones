<?php

namespace Tests\Feature;

use Tests\TestCase;

class PieCourseTransferViewSyntaxTest extends TestCase
{
    public function test_pie_transfer_index_view_compiles_to_valid_php(): void
    {
        $compiled = app('blade.compiler')->compileString(
            file_get_contents(resource_path('views/admin/establecimiento-curso-pie/index.blade.php'))
        );
        $path = tempnam(sys_get_temp_dir(), 'pie-view-');
        $this->assertNotFalse($path);

        try {
            file_put_contents($path, $compiled);
            exec(escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($path), $output, $exitCode);

            $this->assertSame(0, $exitCode, implode("\n", $output));
        } finally {
            @unlink($path);
        }
    }
}
