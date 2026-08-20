<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('descuentos_cgr')) {
            return;
        }

        Schema::table('descuentos_cgr', function (Blueprint $table) {
            if (! Schema::hasColumn('descuentos_cgr', 'codigo_verificacion')) {
                $table->string('codigo_verificacion', 40)
                    ->nullable()
                    ->unique('descuentos_cgr_codigo_verificacion_unique')
                    ->after('observaciones');
            }
            if (! Schema::hasColumn('descuentos_cgr', 'documento_hash')) {
                $table->char('documento_hash', 64)->nullable()->after('codigo_verificacion');
            }
            if (! Schema::hasColumn('descuentos_cgr', 'documento_emitido_en')) {
                $table->timestamp('documento_emitido_en')->nullable()->after('documento_hash');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('descuentos_cgr')) {
            return;
        }

        Schema::table('descuentos_cgr', function (Blueprint $table) {
            if (Schema::hasColumn('descuentos_cgr', 'codigo_verificacion')) {
                $table->dropUnique('descuentos_cgr_codigo_verificacion_unique');
            }

            $columnas = collect(['codigo_verificacion', 'documento_hash', 'documento_emitido_en'])
                ->filter(fn (string $columna) => Schema::hasColumn('descuentos_cgr', $columna))
                ->all();

            if ($columnas !== []) {
                $table->dropColumn($columnas);
            }
        });
    }
};
