<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('declaracion_sostenedores')) {
            Schema::table('declaracion_sostenedores', function (Blueprint $table) {
                if (!Schema::hasColumn('declaracion_sostenedores', 'confirma_registro')) {
                    $table->boolean('confirma_registro')->default(false)->after('observacion_funcionario');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('declaracion_sostenedores')) {
            Schema::table('declaracion_sostenedores', function (Blueprint $table) {
                if (Schema::hasColumn('declaracion_sostenedores', 'confirma_registro')) {
                    $table->dropColumn('confirma_registro');
                }
            });
        }
    }
};
