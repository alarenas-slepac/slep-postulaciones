<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('areas_desempeno', function (Blueprint $table) {
            $table->string('slug', 190)->nullable()->after('nombre');
            $table->unique(['estamento', 'slug'], 'areas_desempeno_estamento_slug_unique');
        });

        // Backfill slug para registros existentes (único por estamento)
        $rows = DB::table('areas_desempeno')->select('id', 'nombre', 'estamento')->orderBy('id')->get();

        $seen = []; // [estamento][slug] => true
        foreach ($rows as $r) {
            $base = Str::slug($r->nombre, '_');
            $slug = $base ?: 'sin_nombre';

            $seen[$r->estamento] ??= [];
            $i = 2;
            while (isset($seen[$r->estamento][$slug])) {
                $slug = $base . '_' . $i;
                $i++;
            }
            $seen[$r->estamento][$slug] = true;

            DB::table('areas_desempeno')->where('id', $r->id)->update(['slug' => $slug]);
        }

        // Si la quieres NOT NULL después del backfill:
        Schema::table('areas_desempeno', function (Blueprint $table) {
            $table->string('slug', 190)->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('areas_desempeno', function (Blueprint $table) {
            $table->dropUnique('areas_desempeno_estamento_slug_unique');
            $table->dropColumn('slug');
        });
    }
};
