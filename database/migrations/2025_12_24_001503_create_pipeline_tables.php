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
        Schema::create('pipelines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('current_version_id')->nullable();
            $table->timestamps();
        });

        Schema::create('pipeline_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('pipeline_id')->constrained('pipelines')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->string('title');
            $table->text('description')->nullable();
            $table->text('changelog')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('status', ['active', 'archived'])->default('active');
            $table->timestamps();

            $table->unique(['pipeline_id', 'version']);
        });

        Schema::table('pipelines', function (Blueprint $table): void {
            $table->foreign('current_version_id')
                ->references('id')
                ->on('pipeline_versions')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pipelines', function (Blueprint $table): void {
            $table->dropForeign(['current_version_id']);
        });

        Schema::dropIfExists('pipeline_versions');
        Schema::dropIfExists('pipelines');
    }
};
