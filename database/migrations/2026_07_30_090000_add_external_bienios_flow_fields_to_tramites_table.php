<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tramites', function (Blueprint $table) {
            if (!Schema::hasColumn('tramites', 'bienios_flujo_externo')) {
                $table->boolean('bienios_flujo_externo')
                    ->default(false)
                    ->after('tipo');
            }

            if (!Schema::hasColumn('tramites', 'detalle_calculo_pdf_path')) {
                $table->string('detalle_calculo_pdf_path', 500)
                    ->nullable()
                    ->after('resolucion_pdf_uploaded_at');
            }

            if (!Schema::hasColumn('tramites', 'detalle_calculo_pdf_uploaded_at')) {
                $table->timestamp('detalle_calculo_pdf_uploaded_at')
                    ->nullable()
                    ->after('detalle_calculo_pdf_path');
            }

            if (!Schema::hasColumn('tramites', 'detalle_calculo_pdf_uploaded_by_user_id')) {
                $table->unsignedBigInteger('detalle_calculo_pdf_uploaded_by_user_id')
                    ->nullable()
                    ->after('detalle_calculo_pdf_uploaded_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tramites', function (Blueprint $table) {
            $columns = [];

            foreach ([
                'detalle_calculo_pdf_uploaded_by_user_id',
                'detalle_calculo_pdf_uploaded_at',
                'detalle_calculo_pdf_path',
                'bienios_flujo_externo',
            ] as $column) {
                if (Schema::hasColumn('tramites', $column)) {
                    $columns[] = $column;
                }
            }

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
