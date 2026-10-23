<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('personal_access_tokens')) {
            Schema::create('personal_access_tokens', function (Blueprint $table): void {
                $table->id();
                $table->morphs('tokenable');
                $table->text('name');
                $table->string('token', 64)->unique();
                $table->text('abilities')->nullable();
                $table->timestamp('last_used_at')->nullable();
                $table->timestamp('expires_at')->nullable()->index();
                $table->timestamps();
            });
        }
        if (!Schema::hasTable('funcionario_api_integrations')) {
            Schema::create('funcionario_api_integrations', function (Blueprint $table): void {
                $table->id();
                $table->string('name', 120)->unique();
                $table->boolean('enabled')->default(true);
                $table->unsignedSmallInteger('requests_per_minute')->default(30);
                $table->timestamps();
            });
        }
        if (!Schema::hasTable('funcionario_consulta_audits')) {
            Schema::create('funcionario_consulta_audits', function (Blueprint $table): void {
                $table->id();
                $table->uuid('request_id')->unique();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->unsignedBigInteger('integration_id')->nullable()->index();
                $table->char('rut_fingerprint', 64)->nullable();
                $table->unsignedSmallInteger('http_status');
                $table->string('outcome', 50);
                $table->timestamp('created_at')->index();
            });
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Permission::findOrCreate('consultar_funcionarios', 'web');
        Permission::findOrCreate('funcionarios_api.manage', 'web');
        Role::where('guard_name', 'web')->whereIn('name', ['admin', 'funcionario_estab', 'funcionario_establecimiento'])
            ->get()->each(fn (Role $role) => $role->givePermissionTo('consultar_funcionarios'));
        Role::where('guard_name', 'web')->where('name', 'admin')->get()
            ->each(fn (Role $role) => $role->givePermissionTo('funcionarios_api.manage'));
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Conservar integraciones, permisos y auditoría. Revocar tokens desde administración.
    }
};
