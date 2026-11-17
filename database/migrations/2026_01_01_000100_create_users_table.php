<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('username', 50)->unique();
            $table->string('email', 254)->unique();
            $table->string('password_hash', 255);
            $table->string('name', 120);
            $table->enum('role', ['admin', 'moderator', 'user'])->default('user');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('auth_version')->default(1);
            $table->timestamp('last_login_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
