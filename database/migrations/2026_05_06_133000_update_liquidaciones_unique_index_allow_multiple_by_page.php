<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private string $table = 'liquidaciones_funcionarios';
    private string $oldIndex = 'liq_func_rut_periodo_dominio_unique';
    private string $newIndex = 'liq_func_rut_periodo_dominio_pagina_unique';

    public function up(): void
    {
        if (!Schema::hasTable($this->table)) {
            return;
        }

        Schema::table($this->table, function (Blueprint $table) {
            if ($this->hasIndex($this->oldIndex)) {
                $table->dropUnique($this->oldIndex);
            }
        });

        Schema::table($this->table, function (Blueprint $table) {
            if (!$this->hasIndex($this->newIndex)) {
                $table->unique(['rut_normalizado', 'anio', 'mes', 'dominio', 'pagina_origen'], $this->newIndex);
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable($this->table)) {
            return;
        }

        Schema::table($this->table, function (Blueprint $table) {
            if ($this->hasIndex($this->newIndex)) {
                $table->dropUnique($this->newIndex);
            }
        });

        Schema::table($this->table, function (Blueprint $table) {
            if (!$this->hasIndex($this->oldIndex)) {
                $table->unique(['rut_normalizado', 'anio', 'mes', 'dominio'], $this->oldIndex);
            }
        });
    }

    private function hasIndex(string $indexName): bool
    {
        try {
            $rows = DB::select("SHOW INDEX FROM `{$this->table}` WHERE Key_name = ?", [$indexName]);
            return count($rows) > 0;
        } catch (Throwable) {
            return false;
        }
    }
};
