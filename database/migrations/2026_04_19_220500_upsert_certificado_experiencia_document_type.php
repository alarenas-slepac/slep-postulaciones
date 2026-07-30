<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('document_types')) {
            return;
        }

        $now = now();
        $existingId = DB::table('document_types')->where('slug', 'certificado_experiencia')->value('id');

        $payload = [
            'label' => 'Certificado de experiencia',
            'required_for' => 'conditional',
            'conditions' => json_encode([
                'optional_min_anios_experiencia' => 1,
            ], JSON_UNESCAPED_UNICODE),
            'template_path' => null,
            'sort_order' => 22,
            'updated_at' => $now,
        ];

        if ($existingId) {
            DB::table('document_types')
                ->where('id', $existingId)
                ->update($payload);
            return;
        }

        DB::table('document_types')->insert($payload + [
            'slug' => 'certificado_experiencia',
            'created_at' => $now,
        ]);
    }

    public function down(): void
    {
        if (!Schema::hasTable('document_types')) {
            return;
        }

        DB::table('document_types')
            ->where('slug', 'certificado_experiencia')
            ->delete();
    }
};
