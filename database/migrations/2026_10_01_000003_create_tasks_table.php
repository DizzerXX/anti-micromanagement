<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('module_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title', 200);
            $table->date('due_date')->nullable();
            $table->enum('current_status', ['todo', 'in_progress', 'blocked', 'done'])->default('todo');
            $table->date('last_logged_for');
            $table->timestamps();

            $table->index(['project_id', 'current_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
