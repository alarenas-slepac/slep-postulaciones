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
        if (!Schema::hasTable('notification_event_settings')) {
            Schema::create('notification_event_settings', function (Blueprint $table): void {
                $table->id(); $table->string('event_key', 100); $table->string('channel', 16);
                $table->boolean('enabled')->nullable();
                $table->string('subject_template', 200)->nullable(); $table->text('body_template')->nullable();
                $table->text('additional_emails')->nullable(); $table->string('recipient_mode', 16)->default('all');
                $table->json('recipient_ids')->nullable(); $table->unsignedInteger('revision')->default(1);
                $table->unsignedBigInteger('updated_by')->nullable(); $table->timestamps();
                $table->unique(['event_key', 'channel']);
            });
        }
        if (!Schema::hasTable('notification_event_changes')) {
            Schema::create('notification_event_changes', function (Blueprint $table): void {
                $table->id(); $table->string('event_key', 100); $table->string('channel', 16);
                $table->unsignedBigInteger('actor_id')->nullable(); $table->text('previous'); $table->text('current');
                $table->timestamps(); $table->index(['event_key', 'channel']);
            });
        }
        if (Schema::hasTable('permissions') && Schema::hasTable('roles')) {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
            Permission::findOrCreate('notifications.manage', 'web');
            Role::where('name', 'admin')->where('guard_name', 'web')->get()->each(fn (Role $role) => $role->givePermissionTo('notifications.manage'));
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }
        if (Schema::hasTable('modules')) {
            DB::table('modules')->insertOrIgnore(['key' => 'admin.notification-events', 'name' => 'Eventos de notificaciones',
                'section' => 'Administración', 'icon' => 'bi-bell', 'sort' => 77, 'created_at' => now(), 'updated_at' => now()]);
            if (Schema::hasTable('module_role') && Schema::hasTable('roles')) {
                $module = DB::table('modules')->where('key', 'admin.notification-events')->value('id');
                foreach (DB::table('roles')->where('name', 'admin')->pluck('id') as $role) {
                    if (!DB::table('module_role')->where('module_id', $module)->where('role_id', $role)->exists()) {
                        DB::table('module_role')->insert(['module_id' => $module, 'role_id' => $role]);
                    }
                }
            }
        }
    }

    public function down(): void
    {
        // Conservar configuración, auditoría y permisos históricos en una reversión.
    }
};
