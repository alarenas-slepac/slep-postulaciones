<?php

namespace Tests\Feature;

use App\Http\Controllers\Gestion\SolicitudReemplazoGestionController;
use App\Models\SolicitudReemplazo;
use App\Models\SolicitudReemplazoJornada;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use ReflectionMethod;
use Tests\TestCase;

class SolicitudReemplazoGestionExportTest extends TestCase
{
    public function test_export_formats_ruts_and_uppercases_names_without_appending_the_replacement_rut(): void
    {
        $user = new User();
        $user->forceFill([
            'rut' => '18501360K',
            'nombres' => 'Nataly Andrea',
            'apellido_paterno' => 'Peña',
            'apellido_materno' => 'Pérez',
        ]);

        $controller = app(SolicitudReemplazoGestionController::class);

        $this->assertSame('18.501.360-K', $this->invokePrivate($controller, 'formatRutChile', ['18501360K']));
        $this->assertSame(
            'PEÑA PÉREZ NATALY ANDREA',
            $this->invokePrivate($controller, 'userNombreCompletoExport', [$user])
        );
        $this->assertSame(
            'PEÑA PÉREZ NATALY ANDREA',
            $this->invokePrivate($controller, 'uppercaseExport', ['Peña Pérez Nataly Andrea'])
        );
    }

    public function test_export_groups_effective_replacement_hours_by_financing_and_education_level(): void
    {
        foreach (['aceptada', 'cerrado', 'cerrada'] as $estado) {
            $solicitud = $this->solicitudWithJornadas($estado, [
                $this->jornada('SUBV GRAL', 10.5, 2),
                $this->jornada('Subvención General', 1, 0.5),
                $this->jornada('SEP', 4, 3.25),
                $this->jornada('P.I.E.', 2.5, 1),
                $this->jornada('PRO-RETENCION', 8, 8),
            ]);

            $this->assertSame([
                'general_basica' => '11,50',
                'general_media' => '2,50',
                'sep_basica' => '4,00',
                'sep_media' => '3,25',
                'pie_basica' => '2,50',
                'pie_media' => '1,00',
            ], $this->invokePrivate(
                app(SolicitudReemplazoGestionController::class),
                'replacementEffectiveHoursExport',
                [$solicitud]
            ), "No se agruparon correctamente las horas para el estado {$estado}.");
        }
    }

    public function test_export_leaves_effective_replacement_hours_blank_outside_accepted_or_closed_states(): void
    {
        $solicitud = $this->solicitudWithJornadas('pendiente_gdp', [
            $this->jornada('SUBV GRAL', 10, 2),
            $this->jornada('SEP', 4, 3),
            $this->jornada('PIE', 2, 1),
        ]);

        $this->assertSame([
            'general_basica' => '',
            'general_media' => '',
            'sep_basica' => '',
            'sep_media' => '',
            'pie_basica' => '',
            'pie_media' => '',
        ], $this->invokePrivate(
            app(SolicitudReemplazoGestionController::class),
            'replacementEffectiveHoursExport',
            [$solicitud]
        ));
    }

    public function test_finiquito_bulk_queries_use_minimal_columns_and_relations(): void
    {
        $controller = app(SolicitudReemplazoGestionController::class);

        $columns = $this->invokePrivate($controller, 'columnasContinuidadFiniquitos', []);
        $relations = $this->invokePrivate($controller, 'relacionesContinuidadFiniquitos', []);

        $this->assertContains('solicitud_anterior_id', $columns);
        $this->assertContains('rut_reemplazo_normalizado', $columns);
        $this->assertContains('postulante:id,user_id', $relations);
        $this->assertContains('contratoPostulante.user:id,rut', $relations);
        $this->assertNotContains('establecimiento:id,rbd,nombre_establecimiento,comuna,sala_cuna', $relations);
    }

    public function test_finiquito_display_relations_keep_signer_fields_separate(): void
    {
        $controller = app(SolicitudReemplazoGestionController::class);

        $baseRelations = $this->invokePrivate($controller, 'relacionesFiniquitos', [false]);
        $signerRelations = $this->invokePrivate($controller, 'relacionesFirmantesFiniquito', []);

        $this->assertNotContains('finiquitoGeneradoPor:id,rut,nombres,apellido_paterno,apellido_materno,email', $baseRelations);
        $this->assertContains('finiquitoFirmadoCargadoPor:id,rut,nombres,apellido_paterno,apellido_materno,email', $signerRelations);
    }

    private function solicitudWithJornadas(string $estado, array $jornadas): SolicitudReemplazo
    {
        $solicitud = new SolicitudReemplazo();
        $solicitud->forceFill(['estado' => $estado]);
        $solicitud->setRelation('jornadas', new Collection($jornadas));

        return $solicitud;
    }

    private function jornada(string $financiamiento, float $basica, float $media): SolicitudReemplazoJornada
    {
        $jornada = new SolicitudReemplazoJornada();
        $jornada->forceFill([
            'financiamiento' => $financiamiento,
            'reemplazo_basica' => $basica,
            'reemplazo_media' => $media,
            'reemplazo_total' => $basica + $media,
        ]);

        return $jornada;
    }

    private function invokePrivate(object $object, string $method, array $arguments): mixed
    {
        $reflection = new ReflectionMethod($object, $method);
        $reflection->setAccessible(true);

        return $reflection->invokeArgs($object, $arguments);
    }
}
