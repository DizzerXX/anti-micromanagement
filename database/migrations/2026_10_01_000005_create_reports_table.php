<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reports', function (Blueprint $table): void {
            $table->id();
            $table->enum('period_type', ['daily', 'weekly']);
            $table->date('start_date');
            $table->date('end_date');
            $table->date('effective_end_date');
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->json('sections');
            $table->json('files');
            $table->string('pdf_path')->nullable();
            $table->string('xlsx_path')->nullable();
            $table->enum('status', ['ready', 'partial', 'failed']);
            $table->text('error')->nullable();
            $table->timestamp('created_at');

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
