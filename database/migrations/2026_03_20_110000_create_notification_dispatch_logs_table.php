<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('notification_dispatch_logs', function (Blueprint $table) {
            $table->id();
            $table->string('channel', 20)->default('mail');
            $table->string('status', 20)->default('pending');
            $table->string('event_key')->nullable()->index();
            $table->string('description')->nullable();
            $table->string('recipient_email')->nullable()->index();
            $table->string('recipient_name')->nullable();
            $table->string('subject')->nullable();
            $table->string('mailable_class')->nullable();
            $table->string('notification_class')->nullable();
            $table->nullableMorphs('notifiable');
            $table->nullableMorphs('related');
            $table->foreignId('triggered_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('context')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_dispatch_logs');
    }
};
