<?php

namespace Tests\Feature;

use App\Http\Controllers\Reemplazos\BuscadorPostulantesController;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use ReflectionMethod;
use Tests\TestCase;

class BuscadorPostulantesReemplazosActivosTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('solicitudes_reemplazo');
        Schema::create('solicitudes_reemplazo', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('postulant_profile_id')->nullable();
            $table->unsignedBigInteger('contrato_trabajo_postulant_profile_id')->nullable();
            $table->unsignedBigInteger('establecimiento_id')->nullable();
            $table->string('estado', 40);
            $table->date('fecha_inicio');
            $table->date('fecha_termino');
            $table->timestamps();
        });

        Carbon::setTestNow(Carbon::parse('2026-08-19 12:00:00', 'America/Santiago'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        Schema::dropIfExists('solicitudes_reemplazo');

        parent::tearDown();
    }

    public function test_marks_accepted_and_closed_requests_only_while_their_dates_are_current(): void
    {
        DB::table('solicitudes_reemplazo')->insert([
            $this->solicitud(1, 'aceptada', '2026-08-01', '2026-08-19'),
            $this->solicitud(2, 'cerrado', '2026-08-19', '2026-08-31'),
            $this->solicitud(3, 'cerrado', '2026-08-01', '2026-08-18'),
            $this->solicitud(4, 'cerrado', '2026-08-20', '2026-08-31'),
            $this->solicitud(5, 'pendiente_gdp', '2026-08-01', '2026-08-31'),
            $this->solicitud(6, 'cerrado', '2026-08-01', '2026-08-31', useContractProfile: true),
            $this->solicitud(7, 'aceptada', '2026-08-01', '2026-08-31', profileId: 99),
        ]);

        $method = new ReflectionMethod(BuscadorPostulantesController::class, 'reemplazosActivosPorPerfil');
        $method->setAccessible(true);
        $result = $method->invoke(app(BuscadorPostulantesController::class), [10]);

        $this->assertSame(
            [1, 2, 6],
            $result->get(10, collect())->pluck('id')->sort()->values()->all()
        );
    }

    private function solicitud(
        int $id,
        string $estado,
        string $inicio,
        string $termino,
        bool $useContractProfile = false,
        int $profileId = 10
    ): array {
        return [
            'id' => $id,
            'postulant_profile_id' => $useContractProfile ? null : $profileId,
            'contrato_trabajo_postulant_profile_id' => $useContractProfile ? $profileId : null,
            'establecimiento_id' => null,
            'estado' => $estado,
            'fecha_inicio' => $inicio,
            'fecha_termino' => $termino,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
