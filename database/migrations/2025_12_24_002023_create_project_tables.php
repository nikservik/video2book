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
        Schema::create('project_tags', function (Blueprint $table): void {
            $table->id();
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('projects', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('tag');
            $table->string('source_filename')->nullable();
            $table->foreignId('pipeline_version_id')->constrained('pipeline_versions');
            $table->jsonb('settings');
            $table->timestamps();

            $table->foreign('tag')
                ->references('slug')
                ->on('project_tags')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });

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
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_steps');
        Schema::dropIfExists('projects');
        Schema::dropIfExists('project_tags');
    }
};
