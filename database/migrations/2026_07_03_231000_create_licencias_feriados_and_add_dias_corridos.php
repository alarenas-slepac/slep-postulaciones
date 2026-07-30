<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('licencias_feriados')) {
            Schema::create('licencias_feriados', function (Blueprint $table) {
                $table->id();
                $table->date('fecha')->unique();
                $table->string('nombre', 190);
                $table->string('tipo', 40)->default('nacional')->index();
                $table->boolean('activo')->default(true)->index();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (Schema::hasTable('licencias_medicas') && ! Schema::hasColumn('licencias_medicas', 'dias_corridos')) {
            Schema::table('licencias_medicas', function (Blueprint $table) {
                $table->unsignedSmallInteger('dias_corridos')->nullable()->after('dias_solicitados');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('licencias_medicas') && Schema::hasColumn('licencias_medicas', 'dias_corridos')) {
            Schema::table('licencias_medicas', function (Blueprint $table) {
                $table->dropColumn('dias_corridos');
            });
        }

        Schema::dropIfExists('licencias_feriados');
    }
};
