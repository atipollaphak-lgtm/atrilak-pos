<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_import_batches', function (Blueprint $table): void {
            $table->id();
            $table->string('source_system', 50);
            $table->string('original_filename');
            $table->string('file_hash', 64);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('total_parsed')->default(0);
            $table->unsignedInteger('ready_count')->default(0);
            $table->unsignedInteger('imported_count')->default(0);
            $table->unsignedInteger('duplicate_count')->default(0);
            $table->unsignedInteger('review_count')->default(0);
            $table->unsignedInteger('invalid_count')->default(0);
            $table->unsignedInteger('not_selected_count')->default(0);
            $table->json('counts')->nullable();
            $table->string('status', 32)->default('pending');
            $table->text('failure_reason')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();

            $table->index(['source_system', 'file_hash']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_import_batches');
    }
};
