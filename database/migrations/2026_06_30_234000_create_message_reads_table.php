<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('message_reads')) {
            Schema::create('message_reads', function (Blueprint $table) {
                $table->id();
                $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('last_read_message_id')->nullable()->constrained('messages')->nullOnDelete();
                $table->timestamp('read_at')->nullable();
                $table->timestamps();
                $table->unique(['conversation_id', 'user_id'], 'message_reads_conversation_user_unique');
                $table->index(['user_id', 'read_at']);
            });

            return;
        }

        Schema::table('message_reads', function (Blueprint $table) {
            if (! Schema::hasColumn('message_reads', 'conversation_id')) {
                $table->foreignId('conversation_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            }
            if (! Schema::hasColumn('message_reads', 'last_read_message_id')) {
                $table->foreignId('last_read_message_id')->nullable()->after('user_id')->constrained('messages')->nullOnDelete();
            }
            if (! Schema::hasColumn('message_reads', 'read_at')) {
                $table->timestamp('read_at')->nullable()->after('last_read_message_id');
            }
            if (! Schema::hasColumn('message_reads', 'created_at')) {
                $table->timestamps();
            }
        });
    }

    public function down(): void
    {
        // No se elimina la tabla para no perder estados de lectura en producción.
    }
};
