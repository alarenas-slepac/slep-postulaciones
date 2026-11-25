<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sned_cuotas')) {
            Schema::create('sned_cuotas', function (Blueprint $table) {
                $table->id();
                $table->unsignedSmallInteger('anio');
                $table->unsignedTinyInteger('numero');
                $table->string('comuna', 100);
                $table->string('comuna_clave', 100);
                $table->unsignedSmallInteger('version')->default(1);
                $table->string('metodo', 20);
                $table->string('mes_pago', 7)->nullable();
                $table->text('instruccion_calculo')->nullable();
                $table->json('resultados')->nullable();
                $table->unsignedBigInteger('creado_por');
                $table->unsignedBigInteger('cerrado_por')->nullable();
                $table->timestamp('cerrado_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('sned_cargas')) {
            Schema::create('sned_cargas', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('cuota_id');
                $table->unsignedTinyInteger('mes');
                $table->string('nombre_archivo');
                $table->string('ruta_archivo');
                $table->char('sha256', 64);
                $table->string('estado', 16);
                $table->json('lectura')->nullable();
                $table->text('error')->nullable();
                $table->unsignedBigInteger('creado_por');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('sned_aportes')) {
            Schema::create('sned_aportes', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('cuota_id');
                $table->unsignedInteger('rbd');
                $table->string('establecimiento');
                $table->unsignedBigInteger('docentes');
                $table->unsignedBigInteger('aaee');
                $table->json('incentivo')->nullable();
                $table->unsignedBigInteger('actualizado_por');
                $table->timestamps();
            });
        }

        if (! Schema::hasIndex('sned_cuotas', 'uq_sned_cuota_version', 'unique')) {
            Schema::table('sned_cuotas', function (Blueprint $table) {
                $table->unique(['anio', 'numero', 'comuna_clave', 'version'], 'uq_sned_cuota_version');
            });
        }
        if (! Schema::hasIndex('sned_cargas', 'ix_sned_carga_mes')) {
            Schema::table('sned_cargas', function (Blueprint $table) {
                $table->index(['cuota_id', 'mes'], 'ix_sned_carga_mes');
            });
        }
        if (! Schema::hasIndex('sned_aportes', 'uq_sned_aporte_rbd', 'unique')) {
            Schema::table('sned_aportes', function (Blueprint $table) {
                $table->unique(['cuota_id', 'rbd'], 'uq_sned_aporte_rbd');
            });
        }

        foreach (['sned_cargas' => 'fk_sned_carga_cuota', 'sned_aportes' => 'fk_sned_aporte_cuota'] as $nombre => $clave) {
            $existe = collect(Schema::getForeignKeys($nombre))->contains(fn ($fk) => $fk['name'] === $clave
                || ($fk['columns'] === ['cuota_id'] && $fk['foreign_table'] === 'sned_cuotas'));
            if (! $existe) {
                Schema::table($nombre, function (Blueprint $table) use ($clave) {
                    $table->foreign('cuota_id', $clave)->references('id')->on('sned_cuotas')->restrictOnDelete();
                });
            }
        }

        if (Schema::hasTable('modules')) {
            $modulo = DB::table('modules')->where('key', 'sned')->value('id');
            if (! $modulo) {
                $modulo = DB::table('modules')->insertGetId([
                    'key' => 'sned', 'name' => 'SNED', 'section' => 'Remuneraciones', 'sort' => 31,
                    'icon' => 'bi-award', 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
            if (Schema::hasTable('roles') && Schema::hasTable('module_role')) {
                foreach (DB::table('roles')->whereIn('name', ['admin', 'funcionario_slep'])->where('guard_name', 'web')->pluck('id') as $rol) {
                    DB::table('module_role')->updateOrInsert(['module_id' => $modulo, 'role_id' => $rol]);
                }
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sned_aportes');
        Schema::dropIfExists('sned_cargas');
        Schema::dropIfExists('sned_cuotas');

        if (Schema::hasTable('modules')) {
            $modulo = DB::table('modules')->where('key', 'sned')->value('id');
            if ($modulo && Schema::hasTable('module_role')) {
                DB::table('module_role')->where('module_id', $modulo)->delete();
            }
            DB::table('modules')->where('key', 'sned')->delete();
        }
    }
};
