<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class GabineteSlepModuleAccessMigrationTest extends TestCase
{
    public function test_creates_gabinete_role_and_grants_requested_module_access(): void
    {
        $this->createPrerequisites();
        $migration = require database_path('migrations/2026_08_03_140000_grant_operations_center_and_messages_to_gabinete.php');

        try {
            $migration->up();
            $migration->up();

            $gabineteId = DB::table('roles')->where('name', 'gabinete_slep')->value('id');
            $comunicacionesId = DB::table('roles')->where('name', 'comunicaciones')->value('id');
            $centroId = DB::table('modules')->where('key', 'centro-operaciones')->value('id');
            $messagesId = DB::table('modules')->where('key', 'messages')->value('id');

            $this->assertNotNull($gabineteId);
            $this->assertDatabaseHas('module_role', ['module_id' => $centroId, 'role_id' => $comunicacionesId]);
            $this->assertDatabaseHas('module_role', ['module_id' => $centroId, 'role_id' => $gabineteId]);
            $this->assertDatabaseHas('module_role', ['module_id' => $messagesId, 'role_id' => $gabineteId]);
            $this->assertSame(3, DB::table('module_role')->count());
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
            $table->unique(['name', 'guard_name']);
        });
        Schema::create('module_role', function (Blueprint $table) {
            $table->foreignId('module_id');
            $table->foreignId('role_id');
            $table->timestamps();
            $table->unique(['module_id', 'role_id']);
        });

        DB::table('roles')->insert([
            'name' => 'comunicaciones',
            'guard_name' => 'web',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('modules')->insert([
            [
                'key' => 'centro-operaciones',
                'name' => 'Centro de Operaciones',
                'section' => 'Operación',
                'icon' => 'bi-broadcast-pin',
                'sort' => 5,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'messages',
                'name' => 'Mensajes',
                'section' => 'Comunicación',
                'icon' => 'bi-chat-dots',
                'sort' => 90,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
