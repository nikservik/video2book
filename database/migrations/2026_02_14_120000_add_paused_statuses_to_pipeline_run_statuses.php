<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE pipeline_runs DROP CONSTRAINT IF EXISTS pipeline_runs_status_check');
            DB::statement("ALTER TABLE pipeline_runs ADD CONSTRAINT pipeline_runs_status_check CHECK (status IN ('queued', 'running', 'paused', 'failed', 'done'))");
            DB::statement("ALTER TABLE pipeline_runs ALTER COLUMN status SET DEFAULT 'queued'");

            DB::statement('ALTER TABLE pipeline_run_steps DROP CONSTRAINT IF EXISTS pipeline_run_steps_status_check');
            DB::statement("ALTER TABLE pipeline_run_steps ADD CONSTRAINT pipeline_run_steps_status_check CHECK (status IN ('pending', 'running', 'paused', 'failed', 'done'))");
            DB::statement("ALTER TABLE pipeline_run_steps ALTER COLUMN status SET DEFAULT 'pending'");

            return;
        }

        Schema::table('pipeline_runs', function (Blueprint $table): void {
            $table->enum('status', ['queued', 'running', 'paused', 'failed', 'done'])
                ->default('queued')
                ->change();
        });

        Schema::table('pipeline_run_steps', function (Blueprint $table): void {
            $table->enum('status', ['pending', 'running', 'paused', 'failed', 'done'])
                ->default('pending')
                ->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("UPDATE pipeline_run_steps SET status = 'pending' WHERE status = 'paused'");
            DB::statement("UPDATE pipeline_runs SET status = 'queued' WHERE status = 'paused'");

            DB::statement('ALTER TABLE pipeline_run_steps DROP CONSTRAINT IF EXISTS pipeline_run_steps_status_check');
            DB::statement("ALTER TABLE pipeline_run_steps ADD CONSTRAINT pipeline_run_steps_status_check CHECK (status IN ('pending', 'running', 'failed', 'done'))");
            DB::statement("ALTER TABLE pipeline_run_steps ALTER COLUMN status SET DEFAULT 'pending'");

            DB::statement('ALTER TABLE pipeline_runs DROP CONSTRAINT IF EXISTS pipeline_runs_status_check');
            DB::statement("ALTER TABLE pipeline_runs ADD CONSTRAINT pipeline_runs_status_check CHECK (status IN ('queued', 'running', 'failed', 'done'))");
            DB::statement("ALTER TABLE pipeline_runs ALTER COLUMN status SET DEFAULT 'queued'");

            return;
        }

        Schema::table('pipeline_run_steps', function (Blueprint $table): void {
            $table->enum('status', ['pending', 'running', 'failed', 'done'])
                ->default('pending')
                ->change();
        });

        Schema::table('pipeline_runs', function (Blueprint $table): void {
            $table->enum('status', ['queued', 'running', 'failed', 'done'])
                ->default('queued')
                ->change();
        });
    }
};
