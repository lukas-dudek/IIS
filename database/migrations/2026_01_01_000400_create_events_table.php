<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table): void {
            $table->id();
            $table->string('slug', 180)->unique();
            $table->string('title', 140);
            $table->text('description');
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->foreignId('venue_id')->constrained('venues')->restrictOnDelete();
            $table->foreignId('category_id')->constrained('categories')->restrictOnDelete();
            $table->unsignedInteger('capacity')->nullable();
            $table->string('cover_path', 255)->nullable();
            $table->text('payment_note')->nullable();
            $table->enum('status', ['draft', 'pending', 'approved', 'rejected', 'cancelled'])->default('draft');
            $table->foreignId('manager_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('rejection_reason', 255)->nullable();
            $table->string('cancelled_reason', 255)->nullable();
            $table->timestamps();

            $table->index(['status', 'starts_at']);
            $table->index(['category_id', 'starts_at']);
            $table->index(['venue_id', 'starts_at']);
            $table->index('manager_id');
            $table->index('submitted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
