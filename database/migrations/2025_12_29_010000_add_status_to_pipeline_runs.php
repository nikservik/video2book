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
        Schema::table('pipeline_runs', function (Blueprint $table): void {
            $table->enum('status', ['queued', 'running', 'failed', 'done'])
                ->default('queued')
                ->after('pipeline_version_id');
        });

        Schema::table('pipeline_run_steps', function (Blueprint $table): void {
            $table->unsignedInteger('position')
                ->default(1)
                ->after('step_version_id');

            $table->enum('status', ['pending', 'running', 'failed', 'done'])
                ->default('pending')
                ->after('result');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pipeline_run_steps', function (Blueprint $table): void {
            $table->dropColumn(['position', 'status']);
        });

        Schema::table('pipeline_runs', function (Blueprint $table): void {
            $table->dropColumn('status');
        });
    }
};
