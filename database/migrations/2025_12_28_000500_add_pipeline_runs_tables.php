<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table): void {
            if (Schema::hasColumn('projects', 'pipeline_version_id')) {
                $table->dropConstrainedForeignId('pipeline_version_id');
            }
        });

        Schema::dropIfExists('project_steps');

        Schema::create('pipeline_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignId('pipeline_version_id')->constrained('pipeline_versions');
            $table->jsonb('state')->nullable();
            $table->timestamps();
        });

        Schema::create('pipeline_run_steps', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('pipeline_run_id')->constrained('pipeline_runs')->cascadeOnDelete();
            $table->foreignId('step_version_id')->constrained('step_versions');
            $table->timestamp('start_time')->nullable();
            $table->timestamp('end_time')->nullable();
            $table->text('error')->nullable();
            $table->text('result')->nullable();
            $table->unsignedInteger('input_tokens')->nullable();
            $table->unsignedInteger('output_tokens')->nullable();
            $table->decimal('cost', 12, 4)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pipeline_run_steps');
        Schema::dropIfExists('pipeline_runs');

        Schema::create('project_steps', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignId('step_version_id')->constrained('step_versions');
            $table->timestamp('start_time')->nullable();
            $table->timestamp('end_time')->nullable();
            $table->text('error')->nullable();
            $table->text('result')->nullable();
            $table->unsignedInteger('input_tokens')->nullable();
            $table->unsignedInteger('output_tokens')->nullable();
            $table->decimal('cost', 12, 4)->nullable();
            $table->timestamps();
        });

        Schema::table('projects', function (Blueprint $table): void {
            if (! Schema::hasColumn('projects', 'pipeline_version_id')) {
                $table->foreignId('pipeline_version_id')->constrained('pipeline_versions');
            }
        });
    }
};
