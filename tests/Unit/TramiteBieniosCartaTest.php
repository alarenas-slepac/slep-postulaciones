<?php

namespace Tests\Unit;

use App\Http\Controllers\TramiteController;
use App\Models\User;
use App\Services\TramiteAutofillService;
use App\Support\TramiteBieniosCarta;
use Illuminate\Http\Request;
use Mockery;
use Tests\TestCase;

class TramiteBieniosCartaTest extends TestCase
{
    public function test_selects_aaee_letter_from_estatuto_or_escalafon(): void
    {
        foreach ([
            ['ASISTENTE DE LA EDUCACIÓN', 'Administrativo'],
            ['Ley 21.109', 'Asistentes de la Educación'],
            ['AAEE', ''],
            ['', 'A.A.E.E.'],
            ['Ley 19.464', 'Paradocente'],
            ['Código del Trabajo', 'Auxiliar'],
            ['Ley 21.109', 'Técnico'],
        ] as [$estatuto, $escalafon]) {
            $this->assertSame('template_relative_path_aaee', TramiteBieniosCarta::configKey($estatuto, $escalafon));
            $this->assertSame('AAEE', TramiteBieniosCarta::estamentoLabel($estatuto, $escalafon));
        }
    }

    public function test_keeps_existing_docente_letter_as_default(): void
    {
        foreach ([
            ['ESTATUTO DOCENTE', 'Docente'],
            ['', ''],
        ] as [$estatuto, $escalafon]) {
            $this->assertSame('template_relative_path', TramiteBieniosCarta::configKey($estatuto, $escalafon));
            $this->assertSame('Docente', TramiteBieniosCarta::estamentoLabel($estatuto, $escalafon));
        }
    }

    public function test_both_configured_letters_exist_and_are_distinct(): void
    {
        $docente = resource_path(config('tramites.tipos.reconocimiento_bienios.template_relative_path'));
        $aaee = resource_path(config('tramites.tipos.reconocimiento_bienios.template_relative_path_aaee'));

        $this->assertFileExists($docente);
        $this->assertFileExists($aaee);
        $this->assertNotSame(hash_file('sha256', $docente), hash_file('sha256', $aaee));
    }

    public function test_download_uses_the_aaee_letter_for_the_applicant(): void
    {
        $user = Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('hasAnyRole')->once()->andReturn(true);

        $autofill = Mockery::mock(TramiteAutofillService::class);
        $autofill->shouldReceive('forUser')->once()->with($user)->andReturn([
            'ok' => true,
            'estatuto' => 'ASISTENTE EDUCACION',
            'escalafon' => 'PARADOCENTE',
        ]);

        $request = Request::create('/tramites/plantilla/reconocimiento_bienios');
        $request->setUserResolver(fn () => $user);

        $response = (new TramiteController)->downloadTemplate($request, 'reconocimiento_bienios', $autofill);

        $this->assertSame(
            resource_path('templates/CARTA BIENIOS AAEE.docx'),
            $response->getFile()->getPathname()
        );
    }
}
