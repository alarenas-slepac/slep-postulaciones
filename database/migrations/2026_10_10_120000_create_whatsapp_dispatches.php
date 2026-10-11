<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('whatsapp_dispatches')) {
            Schema::create('whatsapp_dispatches', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('idempotency_key', 128)->unique();
                $table->string('event_key', 100);
                $table->string('fingerprint', 64);
                $table->text('payload')->nullable(); // Cifrado con APP_KEY; nunca viaja en el job.
                $table->string('status', 24)->default('queued')->index();
                $table->string('error_code', 40)->nullable();
                $table->unsignedBigInteger('triggered_by_user_id')->nullable();
                $table->timestamp('sent_at')->nullable();
                $table->timestamps();
            });
        }

        if (Schema::hasTable('permissions') && Schema::hasTable('roles')) {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
            Permission::findOrCreate('whatsapp.manage', 'web');
            Role::where('name', 'admin')->where('guard_name', 'web')->get()
                ->each(fn (Role $role) => $role->givePermissionTo('whatsapp.manage'));
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }

        if (Schema::hasTable('modules')) {
            DB::table('modules')->insertOrIgnore([
                'key' => 'admin.whatsapp', 'name' => 'WhatsApp · Notificaciones',
                'section' => 'Administración', 'icon' => 'bi-whatsapp', 'sort' => 76,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Reversión conservadora: conservar historial, claves de deduplicación y permisos.
        // Desactivar mediante WHATSAPP_ENABLED=false, sin eliminar datos históricos.
    }
};
