<?php

namespace Tests\Feature;

use App\Models\AreaDesempeno;
use App\Models\DocumentType;
use App\Models\PostulantProfile;
use App\Models\ReemplazoPersonal;
use App\Models\SolicitudReemplazo;
use App\Models\SolicitudReemplazoAutorizacionDocente;
use App\Models\User;
use App\Models\UserDocument;
use App\Services\SolicitudReemplazoAutorizacionDocenteService;
use App\Support\ModuleRegistry;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SolicitudReemplazoAutorizacionDocenteServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');

        Schema::create('document_types', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('label');
            $table->string('required_for')->nullable();
            $table->json('conditions')->nullable();
            $table->string('template_path')->nullable();
            $table->integer('sort_order')->nullable();
            $table->timestamps();
        });

        Schema::create('user_documents', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('document_type_id');
            $table->string('path')->nullable();
            $table->string('original_name')->nullable();
            $table->string('mime')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->string('status')->nullable();
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('user_documents');
        Schema::dropIfExists('document_types');
        parent::tearDown();
    }

    public function test_exige_cinco_documentos_cuando_el_area_es_religion(): void
    {
        $solicitud = $this->solicitud('Religión Católica');
        $service = app(SolicitudReemplazoAutorizacionDocenteService::class);

        foreach ($service->slugsRequeridos($solicitud) as $slug) {
            $this->crearDocumento($slug);
        }

        $documentos = $service->documentosRequeridos($solicitud);

        $this->assertSame([
            'antecedentes_especiales',
            'titulo',
            'titulo_mencion',
            'inhabilidades_menores',
            'idoneidad_religion',
        ], $documentos->pluck('type.slug')->all());
    }

    public function test_exige_inhabilidades_pero_no_idoneidad_fuera_del_area_religion(): void
    {
        $solicitud = $this->solicitud('Educador(a) Diferencial');
        $service = app(SolicitudReemplazoAutorizacionDocenteService::class);

        foreach ($service->slugsRequeridos($solicitud) as $slug) {
            $this->crearDocumento($slug);
        }

        $documentos = $service->documentosRequeridos($solicitud);

        $this->assertSame([
            'antecedentes_especiales',
            'titulo',
            'titulo_mencion',
            'inhabilidades_menores',
        ], $documentos->pluck('type.slug')->all());
        $this->assertNotContains('idoneidad_religion', $documentos->pluck('type.slug')->all());
    }

    public function test_correo_formatea_el_rut_con_puntos_y_guion(): void
    {
        $solicitud = $this->solicitud('Educador(a) Diferencial');
        $solicitud->postulante->user->rut = '188138101';

        $autorizacion = new SolicitudReemplazoAutorizacionDocente();
        $autorizacion->solicitud_reemplazo_id = 30;
        $autorizacion->setRelation('solicitud', $solicitud);

        $html = view('emails.solicitud-autorizacion-docente', [
            'autorizacion' => $autorizacion,
            'documentos' => collect(),
        ])->render();

        $this->assertStringContainsString('<strong>RUT:</strong> 18.813.810-1', $html);
    }

    public function test_bloquea_aprobacion_uatp_sin_numero_de_registro(): void
    {
        $solicitud = $this->solicitud('Educador(a) Diferencial');
        $solicitud->setRelation('autorizacionDocente', null);

        $service = app(SolicitudReemplazoAutorizacionDocenteService::class);

        $this->assertFalse($service->cumpleRegistroParaAprobacionUatp($solicitud));
    }

    public function test_habilita_aprobacion_uatp_al_ingresar_numero_de_registro(): void
    {
        $solicitud = $this->solicitud('Educador(a) Diferencial');
        $autorizacion = new SolicitudReemplazoAutorizacionDocente();
        $autorizacion->numero_autorizacion = 'AUT-2026-00125';
        $solicitud->setRelation('autorizacionDocente', $autorizacion);

        $service = app(SolicitudReemplazoAutorizacionDocenteService::class);

        $this->assertTrue($service->cumpleRegistroParaAprobacionUatp($solicitud));
    }

    public function test_no_bloquea_aprobacion_uatp_en_solicitud_que_no_requiere_autorizacion_docente(): void
    {
        $solicitud = $this->solicitud('Educador(a) Diferencial');
        $solicitud->propone_reemplazo = false;
        $solicitud->setRelation('autorizacionDocente', null);

        $service = app(SolicitudReemplazoAutorizacionDocenteService::class);

        $this->assertTrue($service->cumpleRegistroParaAprobacionUatp($solicitud));
    }

    public function test_informa_documentos_faltantes_antes_de_enviar_el_expediente(): void
    {
        $solicitud = $this->solicitud('Religión Evangélica');
        $service = app(SolicitudReemplazoAutorizacionDocenteService::class);

        foreach (['antecedentes_especiales', 'titulo', 'titulo_mencion'] as $slug) {
            $this->crearDocumento($slug);
        }
        foreach (['inhabilidades_menores', 'idoneidad_religion'] as $slug) {
            DocumentType::query()->create([
                'slug' => $slug,
                'label' => $slug === 'inhabilidades_menores'
                    ? 'Certificado Inhabilidades para trabajar con menores'
                    : 'Idoneidad para Religión',
            ]);
        }

        try {
            $service->documentosRequeridos($solicitud);
            $this->fail('Se esperaba una validación por documentos faltantes.');
        } catch (ValidationException $exception) {
            $mensaje = $exception->errors()['documentos'][0] ?? '';
            $this->assertStringContainsString('Inhabilidades', $mensaje);
            $this->assertStringContainsString('Idoneidad para Religión', $mensaje);
        }
    }

    public function test_bandeja_de_autorizaciones_usa_el_mismo_modulo_de_gestion_reemplazos(): void
    {
        $this->assertSame(
            'gestion.solicitudes-reemplazo',
            ModuleRegistry::moduleKeyFromRouteName('gestion.autorizaciones-docentes.index')
        );
    }

    private function solicitud(string $areaNombre): SolicitudReemplazo
    {
        $user = new User();
        $user->id = 10;
        $user->exists = true;

        $postulante = new PostulantProfile();
        $postulante->id = 20;
        $postulante->user_id = 10;
        $postulante->exists = true;
        $postulante->setRelation('user', $user);
        $user->setRelation('postulantProfile', $postulante);

        $titular = new ReemplazoPersonal();
        $titular->estatuto = 'DOCENTE';

        $area = new AreaDesempeno();
        $area->nombre = $areaNombre;

        $solicitud = new SolicitudReemplazo();
        $solicitud->id = 30;
        $solicitud->propone_reemplazo = true;
        $solicitud->postulant_profile_id = 20;
        $solicitud->setRelation('postulante', $postulante);
        $solicitud->setRelation('funcionarioTitular', $titular);
        $solicitud->setRelation('areaDesempeno', $area);

        return $solicitud;
    }

    private function crearDocumento(string $slug): UserDocument
    {
        $tipo = DocumentType::query()->create([
            'slug' => $slug,
            'label' => str_replace('_', ' ', $slug),
        ]);
        $path = "documentos/{$slug}.pdf";
        Storage::disk('public')->put($path, '%PDF-1.4 prueba');

        return UserDocument::query()->create([
            'user_id' => 10,
            'document_type_id' => $tipo->id,
            'path' => $path,
            'original_name' => "{$slug}.pdf",
            'mime' => 'application/pdf',
            'size' => 20,
            'status' => 'approved',
        ]);
    }
}
