<?php

namespace Tests\Feature;

use App\Services\Certificados\ContratoHistoricoImportService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class ContratoHistoricoImportServiceTest extends TestCase
{
    private string $archivoTemporal;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->crearTablas();
        $temporal = tempnam(sys_get_temp_dir(), 'cert-import-');
        if ($temporal === false) {
            self::fail('No fue posible crear el archivo temporal de prueba.');
        }
        unlink($temporal);
        $this->archivoTemporal = $temporal.'.xlsx';
    }

    protected function tearDown(): void
    {
        if (isset($this->archivoTemporal) && is_file($this->archivoTemporal)) {
            unlink($this->archivoTemporal);
        }

        Schema::dropIfExists('certificado_contratos_historicos');
        Schema::dropIfExists('certificado_importaciones');
        parent::tearDown();
    }

    public function test_importa_validos_omite_invalidos_y_detecta_duplicados(): void
    {
        $this->crearExcel();
        $archivo = new UploadedFile(
            $this->archivoTemporal,
            'historico.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );
        $service = app(ContratoHistoricoImportService::class);

        $importacion = $service->encolar($archivo, 15);
        $procesada = $service->procesar($importacion->id);

        self::assertSame('procesado_con_observaciones', $procesada->estado);
        self::assertSame(4, $procesada->total_filas);
        self::assertSame(2, $procesada->filas_validas);
        self::assertSame(1, $procesada->filas_omitidas);
        self::assertSame(1, $procesada->filas_duplicadas);
        self::assertCount(1, $procesada->errores);
        self::assertDatabaseCount('certificado_contratos_historicos', 2);
        self::assertDatabaseHas('certificado_contratos_historicos', [
            'importacion_id' => $procesada->id,
            'rut_normalizado' => '123456785',
            'termino_indefinido' => true,
        ]);
    }

    private function crearExcel(): void
    {
        $libro = new Spreadsheet;
        $hoja = $libro->getActiveSheet();
        $hoja->fromArray([
            [
                'Rut',
                'Nombre',
                'Establecimiento',
                'Comuna',
                'Fec.Ing.',
                'Fec.Finiq',
                'Calidad Juridica',
                'Régimen Jurídico',
            ],
            [
                '12345678-5',
                'FUNCIONARIA DE PRUEBA',
                'ESCUELA A',
                'CORONEL',
                '29-03-2023',
                '10-04-2023',
                'REEMPLAZO DOCENTE',
                'ESTATUTO DOCENTE',
            ],
            [
                '12345678-5',
                'FUNCIONARIA DE PRUEBA',
                'ESCUELA A',
                'CORONEL',
                '29-03-2023',
                '10-04-2023',
                'REEMPLAZO DOCENTE',
                'ESTATUTO DOCENTE',
            ],
            [
                '12345678-5',
                'FUNCIONARIA DE PRUEBA',
                'ESCUELA A',
                'CORONEL',
                '11-04-2023',
                '10-04-2023',
                'CONTRATA',
                'ESTATUTO DOCENTE',
            ],
            [
                '12345678-5',
                'FUNCIONARIA DE PRUEBA',
                'ESCUELA B',
                'LOTA',
                '11-04-2023',
                'Indefinido',
                'CONTRATA',
                'ESTATUTO DOCENTE',
            ],
        ]);

        (new Xlsx($libro))->save($this->archivoTemporal);
        $libro->disconnectWorksheets();
    }

    private function crearTablas(): void
    {
        Schema::create('certificado_importaciones', function (Blueprint $table) {
            $table->id();
            $table->string('nombre_archivo');
            $table->string('ruta_archivo');
            $table->char('hash_archivo', 64)->unique();
            $table->string('estado');
            $table->boolean('es_vigente')->default(false);
            $table->unsignedInteger('total_filas')->default(0);
            $table->unsignedInteger('filas_validas')->default(0);
            $table->unsignedInteger('filas_omitidas')->default(0);
            $table->unsignedInteger('filas_duplicadas')->default(0);
            $table->json('errores')->nullable();
            $table->unsignedBigInteger('subido_por')->nullable();
            $table->timestamp('procesado_at')->nullable();
            $table->timestamp('activado_at')->nullable();
            $table->timestamps();
        });

        Schema::create('certificado_contratos_historicos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('importacion_id');
            $table->unsignedInteger('fila_origen');
            $table->string('rut_normalizado');
            $table->string('nombre');
            $table->string('establecimiento');
            $table->string('comuna');
            $table->date('fecha_ingreso');
            $table->date('fecha_finiquito')->nullable();
            $table->boolean('termino_indefinido')->default(false);
            $table->string('calidad_juridica');
            $table->string('regimen_juridico');
            $table->char('row_hash', 64);
            $table->timestamps();
            $table->unique(['importacion_id', 'row_hash']);
        });
    }
}
