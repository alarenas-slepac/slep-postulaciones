<?php

namespace Tests\Unit;

use App\Support\TipoReemplazo;
use Tests\TestCase;

class TipoReemplazoTest extends TestCase
{
    public function test_options_keep_numbered_licenses_first_and_other_causes_alphabetical(): void
    {
        $this->assertSame([
            '1. Enfermedad o accidente común',
            '2. Prórroga de medicina preventiva.',
            '3. Licencia maternal pre y postnatal.',
            '4. Enfermedad grave del hijo menor de un año.',
            '5. Accidente del trabajo o del trayecto.',
            '6. Enfermedad profesional',
            '7. Patología del embarazo.',
            '8. Ley Sanna.',
            'Otras',
            'Permiso especial para deportistas (Art 74, Ley 19.712)',
            'Permiso Horas de Lactancia',
            'Permiso Postnatal Parental',
            'Permiso sin goce de sueldo',
            'Reposo Mutualidad (ACHS, MUTUAL, IST o ISL)',
            'Sumario Administrativo',
        ], TipoReemplazo::opciones());
    }

    public function test_previous_license_names_are_presented_with_the_current_names(): void
    {
        $this->assertSame(
            TipoReemplazo::ENFERMEDAD_ACCIDENTE_COMUN,
            TipoReemplazo::etiqueta('Licencia Médica (General)')
        );
        $this->assertSame(
            TipoReemplazo::LICENCIA_MATERNAL,
            TipoReemplazo::etiqueta('Licencia Médica (Pre y/o Post Natal y/o Parental)')
        );
    }

    public function test_it_identifies_reposo_mutualidad(): void
    {
        $this->assertTrue(TipoReemplazo::esReposoMutualidad(TipoReemplazo::REPOSO_MUTUALIDAD));
        $this->assertFalse(TipoReemplazo::esReposoMutualidad('Permiso Postnatal Parental'));
    }
}
