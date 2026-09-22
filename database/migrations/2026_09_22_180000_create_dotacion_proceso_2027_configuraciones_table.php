<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('dotacion_proceso_2027_configuraciones')) {
            Schema::create('dotacion_proceso_2027_configuraciones', function (Blueprint $table) {
                $table->id();
                $table->foreignId('establecimiento_id');
                $table->unsignedSmallInteger('anio')->index();
                $table->string('decision_combinacion', 40)->nullable();
                $table->text('observacion_combinacion')->nullable();
                $table->decimal('max_horas_bloque_1', 8, 2)->nullable();
                $table->decimal('max_horas_bloque_2', 8, 2)->nullable();
                $table->decimal('max_horas_bloque_3', 8, 2)->nullable();
                $table->foreignId('combinacion_confirmada_by')->nullable();
                $table->timestamp('combinacion_confirmada_at')->nullable();
                $table->foreignId('maximos_configurados_by')->nullable();
                $table->timestamp('maximos_configurados_at')->nullable();
                $table->timestamps();
                $table->unique(['establecimiento_id', 'anio'], 'dotacion_proceso_2027_estab_anio_unique');
            });
        }

        // MySQL limita los nombres de restricciones a 64 caracteres. Además, si un
        // intento previo quedó a medio crear, esta verificación termina de aplicar
        // las relaciones que puedan faltar sin borrar los datos existentes.
        $this->ensureForeignKey('establecimiento_id', 'establecimientos', 'd2027cfg_est_fk', 'cascade');
        $this->ensureForeignKey('combinacion_confirmada_by', 'users', 'd2027cfg_comb_by_fk', 'set null');
        $this->ensureForeignKey('maximos_configurados_by', 'users', 'd2027cfg_max_by_fk', 'set null');

        if (Schema::hasTable('dotacion_docente_asignaciones')
            && ! Schema::hasColumn('dotacion_docente_asignaciones', 'excepcion_prelacion')) {
            Schema::table('dotacion_docente_asignaciones', function (Blueprint $table) {
                $table->text('excepcion_prelacion')->nullable()->after('observacion');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('dotacion_docente_asignaciones')
            && Schema::hasColumn('dotacion_docente_asignaciones', 'excepcion_prelacion')) {
            Schema::table('dotacion_docente_asignaciones', function (Blueprint $table) {
                $table->dropColumn('excepcion_prelacion');
            });
        }

        Schema::dropIfExists('dotacion_proceso_2027_configuraciones');
    }

    private function ensureForeignKey(string $column, string $on, string $name, string $onDelete): void
    {
        $tableName = 'dotacion_proceso_2027_configuraciones';
        if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, $column)) {
            return;
        }

        $exists = collect(Schema::getForeignKeys($tableName))
            ->contains(fn (array $foreignKey) => ($foreignKey['columns'] ?? []) === [$column]);
        if ($exists) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($column, $on, $name, $onDelete) {
            $foreign = $table->foreign($column, $name)->references('id')->on($on);
            if ($onDelete === 'cascade') {
                $foreign->cascadeOnDelete();
                return;
            }

            $foreign->nullOnDelete();
        });
    }
};
