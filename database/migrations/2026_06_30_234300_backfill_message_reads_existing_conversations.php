<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('message_reads')
            || ! Schema::hasTable('conversation_participants')
            || ! Schema::hasTable('messages')) {
            return;
        }

        $requiredColumns = [
            'conversation_id',
            'user_id',
            'last_read_message_id',
            'read_at',
        ];

        foreach ($requiredColumns as $column) {
            if (! Schema::hasColumn('message_reads', $column)) {
                return;
            }
        }

        DB::table('conversation_participants as cp')
            ->leftJoin('message_reads as mr', function ($join) {
                $join->on('mr.conversation_id', '=', 'cp.conversation_id')
                    ->on('mr.user_id', '=', 'cp.user_id');
            })
            ->whereNull('mr.id')
            ->select('cp.conversation_id', 'cp.user_id')
            ->orderBy('cp.conversation_id')
            ->chunk(500, function ($participants) {
                $now = now();
                $rows = [];

                foreach ($participants as $participant) {
                    $lastMessageId = DB::table('messages')
                        ->where('conversation_id', $participant->conversation_id)
                        ->max('id');

                    if (! $lastMessageId) {
                        continue;
                    }

                    $rows[] = [
                        'conversation_id' => $participant->conversation_id,
                        'user_id' => $participant->user_id,
                        'last_read_message_id' => $lastMessageId,
                        'read_at' => $now,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                if ($rows !== []) {
                    DB::table('message_reads')->insert($rows);
                }
            });
    }

    public function down(): void
    {
        // No se revierte para no alterar estados reales de lectura en producción.
    }
};
