<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->boolean('is_pinned')->default(false);
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
        });

        // Name is unique among non-archived projects only (SPEC §3).
        DB::statement('CREATE UNIQUE INDEX projects_name_active_unique ON projects (name) WHERE archived_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
