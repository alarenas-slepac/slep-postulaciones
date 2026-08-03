<?php

namespace Tests\Unit;

use App\Support\Messaging\FuncionarioAcDirectory;
use PHPUnit\Framework\TestCase;

class FuncionarioAcDirectoryRolesTest extends TestCase
{
    public function test_internal_messaging_roles_include_funcionario_ac_and_comunicaciones(): void
    {
        $this->assertContains('funcionario_ac', FuncionarioAcDirectory::INTERNAL_ROLES);
        $this->assertContains('comunicaciones', FuncionarioAcDirectory::INTERNAL_ROLES);
    }
}
