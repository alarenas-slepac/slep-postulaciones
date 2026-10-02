<?php

namespace Tests\Support;

use App\Http\Middleware\TouchLastSeen;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

abstract class IsolatedSecurityTestCase extends TestCase
{
    public function createApplication()
    {
        // Se aplica ANTES del bootstrap: ni un config.php cacheado ni variables
        // heredadas pueden seleccionar una conexión real en estas pruebas.
        $settings = [
            'APP_ENV' => 'testing', 'APP_CONFIG_CACHE' => sys_get_temp_dir().'/sga-tests-config-'.bin2hex(random_bytes(12)).'.php',
            // DB_URL podría sobreescribir driver/database aun con SQLite configurado.
            'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:', 'DB_URL' => 'null',
            'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array', 'MAIL_MAILER' => 'array',
        ];
        foreach ($settings as $name => $value) {
            putenv($name.'='.$value);
            $_ENV[$name] = $_SERVER[$name] = $value;
        }

        $app = require dirname(__DIR__, 2).'/bootstrap/app.php';
        $this->traitsUsedByTest = array_flip(class_uses_recursive(static::class));
        if ($app->configurationIsCached()) {
            throw new \RuntimeException('Las pruebas de seguridad no admiten configuración cacheada.');
        }
        $app->make(Kernel::class)->bootstrap();
        if ($app['config']['database.default'] !== 'sqlite'
            || $app['config']['database.connections.sqlite.database'] !== ':memory:'
            || $app['config']['database.connections.sqlite.url'] !== null
            || $app['config']['cache.default'] !== 'array') {
            throw new \RuntimeException('No se confirmó el entorno aislado de pruebas.');
        }

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);
        Storage::fake('public');
        Storage::fake('local');
        $this->assertStringContainsString('/framework/testing/', str_replace('\\', '/', Storage::disk('public')->path('')));
        Log::spy();
        $this->withoutMiddleware(TouchLastSeen::class);

        Schema::create('users', function (Blueprint $table) {
            $table->id(); $table->string('email')->unique(); $table->string('rut')->nullable();
            $table->string('nombres')->nullable(); $table->string('apellido_paterno')->nullable();
            $table->string('apellido_materno')->nullable(); $table->string('password');
            $table->rememberToken(); $table->timestamp('email_verified_at')->nullable();
            $table->timestamp('last_seen_at')->nullable(); $table->softDeletes(); $table->timestamps();
        });
        Schema::create('roles', function (Blueprint $table) {
            $table->id(); $table->string('name'); $table->string('guard_name');
        });
        Schema::create('permissions', function (Blueprint $table) {
            $table->id(); $table->string('name'); $table->string('guard_name');
        });
        Schema::create('model_has_roles', function (Blueprint $table) {
            $table->unsignedBigInteger('role_id'); $table->string('model_type'); $table->unsignedBigInteger('model_id');
        });
        DB::table('roles')->insert([
            ['id' => 1, 'name' => 'postulante', 'guard_name' => 'web'],
            ['id' => 2, 'name' => 'funcionario_ac', 'guard_name' => 'web'],
            ['id' => 3, 'name' => 'admin', 'guard_name' => 'web'],
        ]);
    }

    protected function testUser(int $id = 1, int $role = 1): User
    {
        $user = User::create([
            'rut' => '9900000'.$id.'K', 'email' => 'cuenta'.$id.'@example.test',
            'nombres' => 'Cuenta de prueba', 'password' => 'clave-de-prueba',
        ]);
        $user->forceFill(['email_verified_at' => now()])->save();
        DB::table('model_has_roles')->insert(['role_id' => $role, 'model_type' => User::class, 'model_id' => $user->id]);
        return $user;
    }
}
