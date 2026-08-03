<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MessagesModuleAccessMigrationTest extends TestCase
{
    public function test_grants_messages_access_to_funcionario_ac_and_comunicaciones(): void
    {
        $this->createPrerequisites();

        $migration = require database_path('migrations/2026_08_03_080000_grant_messages_access_to_funcionario_ac_and_comunicaciones.php');

        try {
            $migration->up();
            $migration->up();

            $moduleId = DB::table('modules')->where('key', 'messages')->value('id');
            $roleIds = DB::table('roles')
                ->whereIn('name', ['funcionario_ac', 'comunicaciones'])
                ->pluck('id');

            $this->assertNotNull($moduleId);
            $this->assertSame(2, $roleIds->count());
            $this->assertSame(2, DB::table('module_role')
                ->where('module_id', $moduleId)
                ->whereIn('role_id', $roleIds->all())
                ->count());
        } finally {
            $migration->down();
            Schema::dropIfExists('module_role');
            Schema::dropIfExists('roles');
            Schema::dropIfExists('modules');
        }
    }

    private function createPrerequisites(): void
    {
        Schema::create('modules', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->string('section');
            $table->string('icon')->nullable();
            $table->unsignedInteger('sort')->default(100);
            $table->timestamps();
        });
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
        });
        Schema::create('module_role', function (Blueprint $table) {
            $table->foreignId('module_id');
            $table->foreignId('role_id');
            $table->timestamps();
            $table->unique(['module_id', 'role_id']);
        });

        DB::table('roles')->insert([
            ['name' => 'funcionario_ac', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'comunicaciones', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'postulante', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('modules')->insert([
            'key' => 'messages',
            'name' => 'Mensajes',
            'section' => 'Comunicación',
            'icon' => 'bi-chat-dots',
            'sort' => 90,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
