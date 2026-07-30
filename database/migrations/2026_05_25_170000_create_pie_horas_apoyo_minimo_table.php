<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pie_horas_apoyo_minimo', function (Blueprint $table) {
            $table->id();
            $table->string('regimen_jec', 20)->index();
            $table->unsignedSmallInteger('neet_cantidad_base')->default(5);
            $table->unsignedSmallInteger('neet_horas_base_minutos')->default(0);
            $table->unsignedSmallInteger('neep_cantidad')->index();
            $table->unsignedSmallInteger('neep_horas_minutos')->default(0);
            $table->unsignedSmallInteger('total_crono_minutos')->default(0);
            $table->unsignedSmallInteger('prof_educ_dif_minutos')->default(0);
            $table->unsignedSmallInteger('pae_minutos')->default(0);
            $table->boolean('vigente')->default(true)->index();
            $table->timestamps();

            $table->unique(['regimen_jec', 'neep_cantidad'], 'pie_horas_regimen_neep_unique');
        });

        $now = now();
        $rows = [];
        for ($neep = 1; $neep <= 31; $neep++) {
            $rows[] = [
                'regimen_jec' => 'con_jec',
                'neet_cantidad_base' => 5,
                'neet_horas_base_minutos' => 600,
                'neep_cantidad' => $neep,
                'neep_horas_minutos' => $neep * 180,
                'total_crono_minutos' => 600 + ($neep * 180),
                'prof_educ_dif_minutos' => 360 + ($neep * 135),
                'pae_minutos' => 240 + ($neep * 45),
                'vigente' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];
            $rows[] = [
                'regimen_jec' => 'sin_jec',
                'neet_cantidad_base' => 5,
                'neet_horas_base_minutos' => 420,
                'neep_cantidad' => $neep,
                'neep_horas_minutos' => $neep * 180,
                'total_crono_minutos' => 420 + ($neep * 180),
                'prof_educ_dif_minutos' => 270 + ($neep * 135),
                'pae_minutos' => 150 + ($neep * 45),
                'vigente' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        DB::table('pie_horas_apoyo_minimo')->insert($rows);
    }

    public function down(): void
    {
        Schema::dropIfExists('pie_horas_apoyo_minimo');
    }
};
