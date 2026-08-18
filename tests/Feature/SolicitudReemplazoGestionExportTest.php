<?php

namespace Tests\Feature;

use App\Http\Controllers\Gestion\SolicitudReemplazoGestionController;
use App\Models\SolicitudReemplazo;
use App\Models\SolicitudReemplazoJornada;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
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

    public function test_finiquito_breaks_an_explicit_continuity_when_effective_start_has_a_gap(): void
    {
        $primera = $this->solicitudFiniquito(101, '2026-04-28', '2026-05-13');
        $anterior = $this->solicitudFiniquito(102, '2026-05-14', '2026-06-12');
        $actual = $this->solicitudFiniquito(103, '2026-06-16', '2026-07-12', 102);
        $ultima = $this->solicitudFiniquito(104, '2026-07-13', '2026-08-11', 103);
        $relacionadas = collect(['KK' => collect([$primera, $anterior, $actual, $ultima])]);
        $controller = app(SolicitudReemplazoGestionController::class);

        $continuidadAnterior = $this->invokePrivate($controller, 'continuidadFiniquito', [$anterior, $relacionadas]);
        $continuidadActual = $this->invokePrivate($controller, 'continuidadFiniquito', [$ultima, $relacionadas]);

        $this->assertSame([101, 102], $continuidadAnterior['cadena']->pluck('id')->all());
        $this->assertSame([103, 104], $continuidadActual['cadena']->pluck('id')->all());
        $this->assertSame('2026-06-16', $continuidadActual['inicio']->format('Y-m-d'));
        $this->assertTrue($this->invokePrivate(
            $controller,
            'esSolicitudFinalCadenaFiniquito',
            [$anterior, $relacionadas, Carbon::parse('2026-08-18')]
        ));
    }

    public function test_finiquito_keeps_an_explicit_continuity_when_effective_dates_connect(): void
    {
        $anterior = $this->solicitudFiniquito(101, '2026-05-14', '2026-06-12');
        $actual = $this->solicitudFiniquito(102, '2026-06-13', '2026-07-12', 101);
        $relacionadas = collect(['KK' => collect([$anterior, $actual])]);
        $controller = app(SolicitudReemplazoGestionController::class);

        $continuidad = $this->invokePrivate($controller, 'continuidadFiniquito', [$actual, $relacionadas]);

        $this->assertSame([101, 102], $continuidad['cadena']->pluck('id')->all());
        $this->assertSame('2026-05-14', $continuidad['inicio']->format('Y-m-d'));
        $this->assertFalse($this->invokePrivate(
            $controller,
            'esSolicitudFinalCadenaFiniquito',
            [$anterior, $relacionadas, Carbon::parse('2026-08-18')]
        ));
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

    private function solicitudFiniquito(int $id, string $inicioTrabajo, string $termino, ?int $anteriorId = null): SolicitudReemplazo
    {
        $solicitud = new SolicitudReemplazo();
        $solicitud->forceFill([
            'id' => $id,
            'postulant_profile_id' => 501,
            'contrato_trabajo_postulant_profile_id' => 501,
            'solicitud_anterior_id' => $anteriorId,
            'fecha_inicio_trabajo' => $inicioTrabajo,
            'fecha_termino' => $termino,
            'rut_titular_normalizado' => 'TITULAR-K',
            'rut_reemplazo_normalizado' => 'REEMPLAZO-KK',
        ]);
        $solicitud->setRelation('funcionarioTitular', null);
        $solicitud->setRelation('postulante', null);
        $solicitud->setRelation('contratoPostulante', null);

        return $solicitud;
    }

    private function invokePrivate(object $object, string $method, array $arguments): mixed
    {
        $reflection = new ReflectionMethod($object, $method);
        $reflection->setAccessible(true);

        return $reflection->invokeArgs($object, $arguments);
    }
}
