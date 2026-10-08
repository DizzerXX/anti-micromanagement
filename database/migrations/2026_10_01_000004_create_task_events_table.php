<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Append-only (SPEC §3): no updated_at, no soft deletes.
        Schema::create('task_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->enum('from_status', ['todo', 'in_progress', 'blocked', 'done'])->nullable();
            $table->enum('to_status', ['todo', 'in_progress', 'blocked', 'done']);
            $table->string('note', 500)->nullable();
            $table->date('logged_for');
            $table->timestamp('created_at');

            $table->index(['task_id', 'logged_for', 'created_at', 'id']);
            $table->index('logged_for');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_events');
    }
};
