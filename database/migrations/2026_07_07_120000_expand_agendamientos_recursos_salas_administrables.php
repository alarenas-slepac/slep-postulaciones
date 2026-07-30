<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('agendamiento_recursos_catalogo')) {
            Schema::create('agendamiento_recursos_catalogo', function (Blueprint $table) {
                $table->id();
                $table->string('nombre', 180);
                $table->string('slug', 80)->unique();
                $table->string('tipo', 40)->default('sala');
                $table->string('ubicacion', 180)->nullable();
                $table->text('descripcion')->nullable();
                $table->boolean('requiere_aprobacion')->default(false);
                $table->boolean('activo')->default(true);
                $table->foreignId('created_by')->nullable();
                $table->foreignId('updated_by')->nullable();
                $table->timestamps();

                $table->index(['tipo', 'activo'], 'agrcat_tipo_activo_idx');
            });
        }

        if (! Schema::hasTable('agendamiento_recurso_administradores')) {
            Schema::create('agendamiento_recurso_administradores', function (Blueprint $table) {
                $table->id();
                $table->foreignId('recurso_id');
                $table->foreignId('user_id');
                $table->foreignId('created_by')->nullable();
                $table->timestamps();

                $table->unique(['recurso_id', 'user_id'], 'agra_recurso_user_unique');
                $table->index('user_id', 'agra_user_idx');
            });

            Schema::table('agendamiento_recurso_administradores', function (Blueprint $table) {
                $table->foreign('recurso_id', 'agra_recurso_fk')->references('id')->on('agendamiento_recursos_catalogo')->cascadeOnDelete();
                $table->foreign('user_id', 'agra_user_fk')->references('id')->on('users')->cascadeOnDelete();
                $table->foreign('created_by', 'agra_created_fk')->references('id')->on('users')->nullOnDelete();
            });
        }

        if (Schema::hasTable('agendamientos_recursos')) {
            Schema::table('agendamientos_recursos', function (Blueprint $table) {
                if (! Schema::hasColumn('agendamientos_recursos', 'recurso_catalogo_id')) {
                    $table->foreignId('recurso_catalogo_id')->nullable()->after('id');
                }
                if (! Schema::hasColumn('agendamientos_recursos', 'solicitado_by')) {
                    $table->foreignId('solicitado_by')->nullable()->after('solicitante_user_id');
                }
                if (! Schema::hasColumn('agendamientos_recursos', 'aprobado_by')) {
                    $table->foreignId('aprobado_by')->nullable()->after('motivo_anulacion');
                }
                if (! Schema::hasColumn('agendamientos_recursos', 'aprobado_at')) {
                    $table->timestamp('aprobado_at')->nullable()->after('aprobado_by');
                }
                if (! Schema::hasColumn('agendamientos_recursos', 'rechazado_by')) {
                    $table->foreignId('rechazado_by')->nullable()->after('aprobado_at');
                }
                if (! Schema::hasColumn('agendamientos_recursos', 'rechazado_at')) {
                    $table->timestamp('rechazado_at')->nullable()->after('rechazado_by');
                }
                if (! Schema::hasColumn('agendamientos_recursos', 'motivo_rechazo')) {
                    $table->text('motivo_rechazo')->nullable()->after('rechazado_at');
                }
            });

            // Se omiten foreign keys en la tabla existente para evitar fallos por migraciones parciales o nombres de índices heredados en MySQL/cPanel.
        }

        $this->crearRolSecretaria();
        $this->sembrarRecursosIniciales();
        $this->backfillAgendamientosExistentes();
    }

    public function down(): void
    {
        if (Schema::hasTable('agendamientos_recursos')) {
            Schema::table('agendamientos_recursos', function (Blueprint $table) {
                foreach (['recurso_catalogo_id', 'solicitado_by', 'aprobado_by', 'aprobado_at', 'rechazado_by', 'rechazado_at', 'motivo_rechazo'] as $column) {
                    if (Schema::hasColumn('agendamientos_recursos', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        Schema::dropIfExists('agendamiento_recurso_administradores');
        Schema::dropIfExists('agendamiento_recursos_catalogo');
    }

    private function crearRolSecretaria(): void
    {
        if (! Schema::hasTable('roles')) {
            return;
        }

        $exists = DB::table('roles')->where('name', 'secretaria_direccion_ejecutiva')->exists();
        if (! $exists) {
            DB::table('roles')->insert([
                'name' => 'secretaria_direccion_ejecutiva',
                'guard_name' => 'web',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function sembrarRecursosIniciales(): void
    {
        if (! Schema::hasTable('agendamiento_recursos_catalogo')) {
            return;
        }

        $recursos = [
            ['slug' => 'proyector', 'nombre' => 'Proyector', 'tipo' => 'proyector', 'ubicacion' => 'SLEP Andalién Costa', 'requiere_aprobacion' => false],
            ['slug' => 'sala_gdp', 'nombre' => 'Sala de Reuniones GDP', 'tipo' => 'sala', 'ubicacion' => 'Gestión y Desarrollo de Personas', 'requiere_aprobacion' => false],
            ['slug' => 'sala_direccion_ejecutiva_gabinete', 'nombre' => 'Sala Dirección Ejecutiva y Gabinete', 'tipo' => 'sala', 'ubicacion' => 'Dirección Ejecutiva', 'requiere_aprobacion' => true],
            ['slug' => 'sala_1_4to_piso', 'nombre' => 'Sala 1 4to Piso', 'tipo' => 'sala', 'ubicacion' => '4to Piso', 'requiere_aprobacion' => true],
            ['slug' => 'sala_2_4to_piso', 'nombre' => 'Sala 2 4to Piso', 'tipo' => 'sala', 'ubicacion' => '4to Piso', 'requiere_aprobacion' => true],
        ];

        foreach ($recursos as $recurso) {
            DB::table('agendamiento_recursos_catalogo')->updateOrInsert(
                ['slug' => $recurso['slug']],
                array_merge($recurso, ['activo' => true, 'updated_at' => now(), 'created_at' => now()])
            );
        }
    }

    private function backfillAgendamientosExistentes(): void
    {
        if (! Schema::hasTable('agendamientos_recursos') || ! Schema::hasColumn('agendamientos_recursos', 'recurso_catalogo_id')) {
            return;
        }

        $catalogo = DB::table('agendamiento_recursos_catalogo')->pluck('id', 'slug');

        if (isset($catalogo['proyector'])) {
            DB::table('agendamientos_recursos')
                ->whereNull('recurso_catalogo_id')
                ->where('tipo_recurso', 'proyector')
                ->update(['recurso_catalogo_id' => $catalogo['proyector']]);
        }

        if (isset($catalogo['sala_gdp'])) {
            DB::table('agendamientos_recursos')
                ->whereNull('recurso_catalogo_id')
                ->where('tipo_recurso', 'sala_gdp')
                ->update(['recurso_catalogo_id' => $catalogo['sala_gdp']]);
        }

        DB::table('agendamientos_recursos')
            ->whereNull('solicitado_by')
            ->whereNotNull('solicitante_user_id')
            ->update(['solicitado_by' => DB::raw('solicitante_user_id')]);
    }
};
