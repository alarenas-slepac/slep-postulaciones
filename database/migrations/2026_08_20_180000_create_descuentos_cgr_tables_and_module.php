<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('utm_valores')) {
            Schema::create('utm_valores', function (Blueprint $table) {
                $table->id();
                $table->unsignedSmallInteger('anio');
                $table->unsignedTinyInteger('mes');
                $table->decimal('valor', 12, 2);
                $table->foreignId('creado_por_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('actualizado_por_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->unique(['anio', 'mes'], 'utm_valores_anio_mes_unique');
            });
        }

        if (! Schema::hasTable('descuentos_cgr')) {
            Schema::create('descuentos_cgr', function (Blueprint $table) {
                $table->id();
                $table->string('rut', 12)->index();
                $table->string('nombre');
                $table->string('numero_resolucion', 100);
                $table->date('fecha_resolucion')->nullable();
                $table->unsignedBigInteger('deuda_definitiva_pesos');
                $table->decimal('deuda_equivalente_utm', 14, 4);
                $table->decimal('cuota_utm', 14, 4);
                $table->unsignedSmallInteger('numero_cuotas');
                $table->decimal('tasa_interes_anual', 8, 4);
                $table->decimal('tasa_interes_mensual', 8, 4);
                $table->date('fecha_primer_descuento');
                $table->string('resolucion_pdf_path');
                $table->string('resolucion_pdf_nombre');
                $table->unsignedBigInteger('resolucion_pdf_tamano')->nullable();
                $table->text('observaciones')->nullable();
                $table->foreignId('creado_por_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('actualizado_por_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['fecha_primer_descuento', 'rut']);
            });
        }

        $this->registrarModulo();
    }

    public function down(): void
    {
        if (Schema::hasTable('modules')) {
            $moduleId = DB::table('modules')->where('key', 'descuentos-cgr')->value('id');
            if ($moduleId) {
                if (Schema::hasTable('module_role')) {
                    DB::table('module_role')->where('module_id', $moduleId)->delete();
                }
                DB::table('modules')->where('id', $moduleId)->delete();
            }
        }

        Schema::dropIfExists('descuentos_cgr');
        Schema::dropIfExists('utm_valores');
    }

    private function registrarModulo(): void
    {
        if (! Schema::hasTable('modules')) {
            return;
        }

        $moduleId = DB::table('modules')->where('key', 'descuentos-cgr')->value('id');
        $attributes = [
            'name' => 'Descuentos CGR',
            'section' => 'Remuneraciones',
            'icon' => 'bi bi-bank',
            'sort' => 30,
            'updated_at' => now(),
        ];

        if ($moduleId) {
            DB::table('modules')->where('id', $moduleId)->update($attributes);
        } else {
            $moduleId = DB::table('modules')->insertGetId($attributes + [
                'key' => 'descuentos-cgr',
                'created_at' => now(),
            ]);
        }

        if (! Schema::hasTable('roles') || ! Schema::hasTable('module_role')) {
            return;
        }

        $hasTimestamps = Schema::hasColumn('module_role', 'created_at')
            && Schema::hasColumn('module_role', 'updated_at');

        foreach (DB::table('roles')->whereIn('name', ['admin', 'funcionario_slep'])->pluck('id') as $roleId) {
            $exists = DB::table('module_role')
                ->where('module_id', $moduleId)
                ->where('role_id', $roleId)
                ->exists();

            if (! $exists) {
                $payload = ['module_id' => $moduleId, 'role_id' => $roleId];
                if ($hasTimestamps) {
                    $payload += ['created_at' => now(), 'updated_at' => now()];
                }
                DB::table('module_role')->insert($payload);
            }
        }
    }
};
