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
        Schema::create('steps', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('pipeline_id')->constrained('pipelines')->cascadeOnDelete();
            $table->string('name');
            $table->foreignId('current_version_id')->nullable();
            $table->timestamps();
        });

        Schema::create('step_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('step_id')->constrained('steps')->cascadeOnDelete();
            $table->enum('type', ['transcribe', 'text', 'glossary']);
            $table->unsignedInteger('version');
            $table->text('description')->nullable();
            $table->text('prompt')->nullable();
            $table->jsonb('settings');
            $table->enum('status', ['active', 'disabled'])->default('active');
            $table->timestamps();

            $table->unique(['step_id', 'version']);
        });

        Schema::create('pipeline_version_steps', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('pipeline_version_id')->constrained('pipeline_versions')->cascadeOnDelete();
            $table->foreignId('step_version_id')->constrained('step_versions')->cascadeOnDelete();
            $table->unsignedInteger('position');
            $table->timestamps();

            $table->unique(['pipeline_version_id', 'position']);
        });

        Schema::table('steps', function (Blueprint $table): void {
            $table->foreign('current_version_id')
                ->references('id')
                ->on('step_versions')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('steps', function (Blueprint $table): void {
            $table->dropForeign(['current_version_id']);
        });

        Schema::dropIfExists('pipeline_version_steps');
        Schema::dropIfExists('step_versions');
        Schema::dropIfExists('steps');
    }
};
