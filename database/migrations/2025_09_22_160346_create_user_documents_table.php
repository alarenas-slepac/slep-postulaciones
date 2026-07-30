<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('user_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_type_id')->constrained('document_types')->cascadeOnDelete();

            $table->string('path');          // storage path (disk public)
            $table->string('original_name'); // nombre original
            $table->string('mime', 100)->nullable();
            $table->unsignedBigInteger('size')->default(0);

            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->text('reviewer_comment')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();

            $table->timestamps();

            // 1 documento por tipo por usuario (la última versión reemplaza a la anterior)
            $table->unique(['user_id', 'document_type_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_documents');
    }
};
