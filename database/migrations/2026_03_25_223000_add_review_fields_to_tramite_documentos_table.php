<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tramite_documentos', function (Blueprint $table) {
            if (!Schema::hasColumn('tramite_documentos', 'estado_revision')) {
                $table->string('estado_revision', 30)->nullable()->after('fecha_termino');
                $table->index(['tramite_id', 'estado_revision'], 'tramite_documentos_tramite_estado_revision_idx');
            }

            if (!Schema::hasColumn('tramite_documentos', 'reviewed_by')) {
                $table->foreignId('reviewed_by')->nullable()->after('estado_revision')->constrained('users')->nullOnDelete();
            }

            if (!Schema::hasColumn('tramite_documentos', 'reviewed_at')) {
                $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
            }

            if (!Schema::hasColumn('tramite_documentos', 'revision_observacion')) {
                $table->text('revision_observacion')->nullable()->after('reviewed_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tramite_documentos', function (Blueprint $table) {
            if (Schema::hasColumn('tramite_documentos', 'revision_observacion')) {
                $table->dropColumn('revision_observacion');
            }

            if (Schema::hasColumn('tramite_documentos', 'reviewed_at')) {
                $table->dropColumn('reviewed_at');
            }

            if (Schema::hasColumn('tramite_documentos', 'reviewed_by')) {
                $table->dropConstrainedForeignId('reviewed_by');
            }

            if (Schema::hasColumn('tramite_documentos', 'estado_revision')) {
                $table->dropIndex('tramite_documentos_tramite_estado_revision_idx');
                $table->dropColumn('estado_revision');
            }
        });
    }
};
