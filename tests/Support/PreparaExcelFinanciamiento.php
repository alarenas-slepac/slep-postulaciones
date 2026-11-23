<?php

namespace Tests\Support;

use App\Jobs\GenerarExcelFinanciamiento;
use App\Models\DotacionFinanciamientoExportacion;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

trait PreparaExcelFinanciamiento
{
    protected function instalarExportacionFinanciamiento(): void
    {
        foreach (['0001_01_01_000002_create_jobs_table.php', '2026_11_23_200000_create_dotacion_financiamiento_exports.php'] as $file) {
            (require database_path('migrations/'.$file))->up();
        }
        config(['queue.default' => 'sync']);
    }

    protected function abrirExcelFinanciamiento(int $anio, array $filtros = []): Spreadsheet
    {
        $post = $this->post(route('admin.dotacion-establecimiento.financiamiento.store'), ['anio' => $anio] + $filtros)
            ->assertStatus(303);
        $solicitud = DotacionFinanciamientoExportacion::latest('id')->firstOrFail();
        $post->assertRedirect(route('admin.dotacion-establecimiento.financiamiento.show', $solicitud->id));
        $this->assertSame('pendiente', $solicitud->estado);
        $this->assertSame(1, DB::table('jobs')->where('queue', GenerarExcelFinanciamiento::COLA)->count());

        $this->assertSame(0, Artisan::call('queue:work', [
            'connection' => GenerarExcelFinanciamiento::CONEXION, '--queue' => GenerarExcelFinanciamiento::COLA, '--once' => true,
        ]));
        $this->assertSame('listo', $solicitud->fresh()->estado);
        $respuesta = $this->get(route('admin.dotacion-establecimiento.financiamiento.download', $solicitud->id))
            ->assertOk()->assertDownload($solicitud->nombreDescarga());

        return IOFactory::load($respuesta->baseResponse->getFile()->getPathname());
    }
}
