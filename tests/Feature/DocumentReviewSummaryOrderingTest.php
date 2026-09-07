<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\DocumentReviewController;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DocumentReviewSummaryOrderingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        // La búsqueda existente usa CONCAT de MySQL; se emula solo en SQLite de pruebas.
        DB::connection()->getPdo()->sqliteCreateFunction('CONCAT', fn (...$values) => implode('', $values));
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('nombres');
            $table->string('apellido_paterno')->default('Prueba');
            $table->string('apellido_materno')->default('');
            $table->string('email');
            $table->string('rut')->nullable();
            $table->softDeletes();
        });
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('guard_name');
        });
        Schema::create('model_has_roles', function (Blueprint $table) {
            $table->unsignedBigInteger('role_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
        });
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('guard_name');
        });
        Schema::create('postulant_profiles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('estamento');
            $table->unsignedBigInteger('area_desempeno_id')->nullable();
        });
        Schema::create('area_desempenos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
        });
        Schema::create('document_types', function (Blueprint $table) {
            $table->id();
            $table->string('slug');
            $table->string('label');
            $table->string('required_for');
            $table->text('conditions')->nullable();
            $table->integer('sort_order')->nullable();
        });
        Schema::create('user_documents', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('document_type_id');
            $table->string('status');
            $table->timestamps();
        });
        DB::table('roles')->insert([
            ['id' => 1, 'name' => 'postulante', 'guard_name' => 'web'],
            ['id' => 2, 'name' => 'funcionario', 'guard_name' => 'web'],
            ['id' => 3, 'name' => 'admin', 'guard_name' => 'web'],
        ]);
        DB::table('document_types')->insert([
            ['id' => 1, 'slug' => 'identidad', 'label' => 'Identidad', 'required_for' => 'both'],
            ['id' => 2, 'slug' => 'solo_asistente', 'label' => 'Solo asistente', 'required_for' => 'asistente'],
        ]);
    }

    public function test_ordena_pendientes_visibles_luego_revisados_y_sin_documentos(): void
    {
        $this->user(1, 'A revisado');
        $this->document(1, 'approved', '2025-01-01');
        $this->document(1, 'pending', '2024-01-01', 2); // No corresponde al perfil.
        $this->user(2, 'B reciente', 2);
        $this->document(2, 'pending', '2026-08-20');
        $this->user(3, 'C antiguo');
        $this->document(3, 'pending', '2026-08-01');
        $this->document(3, 'approved', '2024-01-01');
        $this->user(4, 'D sin archivos');
        $this->user(5, 'E rechazado');
        $this->document(5, 'rejected', '2026-07-01');
        $this->user(6, 'F admin', 3);
        $this->document(6, 'pending', '2020-01-01');
        $data = $this->summary();

        $this->assertSame([3, 2, 1, 5, 4], $data['rows']->getCollection()->pluck('user.id')->all());
        $this->assertSame('2026-08-01', $data['rows'][0]['oldest_pending_at']->toDateString());
        $this->assertSame(2, $data['globalPendingCount']);
        $this->assertSame(2, $data['globalPendingPeopleCount']);
        $this->assertSame(2, $data['globalReviewedPeopleCount']);
    }

    public function test_ordena_antes_de_paginar_y_conserva_busqueda(): void
    {
        for ($id = 1; $id <= 12; $id++) {
            $this->user($id, 'Usuario '.str_pad((string) $id, 2, '0', STR_PAD_LEFT));
            $this->document($id, 'pending', sprintf('2026-08-%02d', 13 - $id));
        }
        $first = $this->summary(['per_page' => 10]);
        $second = $this->summary(['per_page' => 10, 'page' => 2]);
        $this->assertSame(range(12, 3), $first['rows']->getCollection()->pluck('user.id')->all());
        $this->assertSame([2, 1], $second['rows']->getCollection()->pluck('user.id')->all());
        $filtered = $this->summary(['q' => 'Usuario 0', 'per_page' => 10]);
        $this->assertSame(range(9, 1), $filtered['rows']->getCollection()->pluck('user.id')->all());
        $this->assertSame(12, $filtered['globalPendingPeopleCount']);
        $this->assertStringContainsString('q=Usuario%200', $filtered['rows']->url(2));
    }

    public function test_reenvios_fechas_nulas_y_empates_no_dejan_pendientes_detras_de_revisados(): void
    {
        foreach ([1 => 'Z antiguo', 2 => 'B empate', 3 => 'A empate', 4 => 'Sin fecha', 5 => 'Revisado'] as $id => $name) {
            $this->user($id, $name);
        }
        $this->document(1, 'pending', '2026-09-01');
        DB::table('user_documents')->where('user_id', 1)->update(['created_at' => '2020-01-01']);
        $this->document(2, 'pending', '2026-08-01');
        $this->document(3, 'pending', null);
        DB::table('user_documents')->where('user_id', 3)->update(['created_at' => '2026-08-01']);
        $this->document(4, 'pending', null);
        $this->document(5, 'approved', '2025-01-01');
        $data = $this->summary();
        $this->assertSame([3, 2, 1, 4, 5], $data['rows']->getCollection()->pluck('user.id')->all());
    }

    public function test_excel_consolida_rut_y_genera_un_xlsx_con_todos_los_pendientes(): void
    {
        $this->user(1, '=Nombre de prueba');
        $this->user(2, 'Segunda cuenta');
        $this->user(3, 'Reciente', 2);
        $this->user(4, 'Oculto');
        $this->user(5, 'Operador', 3);
        DB::table('users')->where('id', 1)->update(['rut' => '12.345.678-5']);
        DB::table('users')->where('id', 2)->update(['rut' => '123456785']);
        DB::table('users')->where('id', 3)->update(['rut' => '11.111.111-1']);
        $this->document(1, 'pending', '2026-08-20');
        $this->document(2, 'pending', '2026-08-01');
        $this->document(2, 'approved', '2020-01-01');
        $this->document(3, 'pending', '2026-08-25');
        $this->document(4, 'pending', '2020-01-01', 2);
        $this->document(5, 'pending', '2020-01-01'); // Admin no es parte de la nómina.
        $this->actingAs(User::findOrFail(5));

        $request = Request::create('/admin/documentos', 'GET', ['export' => 'pending-xlsx', 'page' => 99, 'q' => 'no coincide']);
        $this->app->instance('request', $request);
        $response = app(DocumentReviewController::class)->index($request);
        $this->assertInstanceOf(\Symfony\Component\HttpFoundation\StreamedResponse::class, $response);
        $this->assertStringContainsString('.xlsx', $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));

        // Inspección del XLSX real, no solo de los datos previos a serializar.
        $temp = tempnam(sys_get_temp_dir(), 'docs_export_test_');
        try {
            // El escritor genera el archivo temporal directamente, sin datos reales.
            $export = app(\App\Exports\DocumentosPendientesExport::class);
            $users = User::whereIn('id', [1, 2, 3, 4])->with(['documents', 'postulantProfile.areaDesempeno'])->get();
            $rows = $export->rows($users, \App\Models\DocumentType::all());
            $book = $export->workbook($rows);
            (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($book))->save($temp);
            $book->disconnectWorksheets();
            $loaded = \PhpOffice\PhpSpreadsheet\IOFactory::load($temp);
            $sheet = $loaded->getActiveSheet();
            $this->assertSame(3, $sheet->getHighestRow());
            $this->assertSame('12.345.678-5', $sheet->getCell('A2')->getValue());
            $this->assertSame('11.111.111-1', $sheet->getCell('A3')->getValue());
            $this->assertSame(2, $sheet->getCell('E2')->getValue());
            $this->assertSame("1\n2", $sheet->getCell('D2')->getValue());
            $this->assertSame('Identidad (2)', $sheet->getCell('G2')->getValue());
            $this->assertSame('s', $sheet->getCell('B2')->getDataType());
            $this->assertStringStartsWith('=Nombre', $sheet->getCell('B2')->getValue());
            $this->assertSame('n', $sheet->getCell('F2')->getDataType());
            $this->assertSame('A1:H3', $sheet->getAutoFilter()->getRange());
            $this->assertSame('A2', $sheet->getFreezePane());
            $loaded->disconnectWorksheets();
        } finally {
            unlink($temp);
        }
        ob_start();
        try {
            $response->sendContent();
            $bytes = ob_get_contents();
        } finally {
            ob_end_clean();
        }
        $this->assertStringStartsWith('PK', $bytes);
        $downloadTemp = tempnam(sys_get_temp_dir(), 'docs_download_test_');
        try {
            file_put_contents($downloadTemp, $bytes);
            $downloaded = \PhpOffice\PhpSpreadsheet\IOFactory::load($downloadTemp);
            $this->assertSame(3, $downloaded->getActiveSheet()->getHighestRow());
            $this->assertSame('12.345.678-5', $downloaded->getActiveSheet()->getCell('A2')->getValue());
            $this->assertSame(2, $downloaded->getActiveSheet()->getCell('E2')->getValue());
            $downloaded->disconnectWorksheets();
        } finally {
            unlink($downloadTemp);
        }
    }

    public function test_excel_no_fusiona_rut_ausente_o_invalido_y_deja_sin_fecha_al_final(): void
    {
        foreach (range(1, 4) as $id) {
            $this->user($id, 'Prueba '.$id);
            $this->document($id, 'pending', $id === 1 ? null : '2026-08-01');
        }
        DB::table('users')->whereIn('id', [3, 4])->update(['rut' => '12345678-0']);
        $export = app(\App\Exports\DocumentosPendientesExport::class);
        $rows = $export->rows(User::with(['documents', 'postulantProfile.areaDesempeno'])->get(), \App\Models\DocumentType::all());
        $this->assertCount(4, $rows);
        $this->assertSame('1', $rows->last()['user_ids']);
        foreach ($rows as $row) {
            $this->assertStringContainsString('registro separado', $row['notes']);
        }
        $book = $export->workbook($rows);
        $this->assertSame('Sin fecha registrada', $book->getActiveSheet()->getCell('F5')->getValue());
        $book->disconnectWorksheets();
        $empty = $export->workbook(collect());
        $this->assertSame(1, $empty->getActiveSheet()->getHighestRow());
        $empty->disconnectWorksheets();
    }

    public function test_exportacion_rechaza_a_un_postulante(): void
    {
        $this->user(1, 'Sin permiso');
        $this->actingAs(User::findOrFail(1));
        $request = Request::create('/admin/documentos', 'GET', ['export' => 'pending-xlsx']);
        $this->app->instance('request', $request);
        $this->expectException(\Illuminate\Auth\Access\AuthorizationException::class);
        app(DocumentReviewController::class)->index($request);
    }

    private function user(int $id, string $name, int $role = 1): void
    {
        DB::table('users')->insert(['id' => $id, 'nombres' => $name, 'email' => 'usuario'.$id.'@example.test']);
        DB::table('model_has_roles')->insert(['role_id' => $role, 'model_type' => User::class, 'model_id' => $id]);
        DB::table('postulant_profiles')->insert(['user_id' => $id, 'estamento' => 'docente']);
    }

    private function document(int $id, string $status, ?string $date, int $type = 1): void
    {
        DB::table('user_documents')->insert([
            'user_id' => $id, 'document_type_id' => $type, 'status' => $status,
            'updated_at' => $date, 'created_at' => $date,
        ]);
    }

    private function summary(array $query = []): array
    {
        $request = Request::create('/admin/documentos', 'GET', $query);
        $this->app->instance('request', $request);

        return app(DocumentReviewController::class)->index($request)->getData();
    }
}
