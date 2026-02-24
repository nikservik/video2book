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
        Schema::table('users', function (Blueprint $table): void {
            $table->softDeletes();
        });

        Schema::table('projects', function (Blueprint $table): void {
            $table->softDeletes();
        });

        Schema::table('lessons', function (Blueprint $table): void {
            $table->softDeletes();
        });

        Schema::table('pipeline_runs', function (Blueprint $table): void {
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pipeline_runs', function (Blueprint $table): void {
            $table->dropSoftDeletes();
        });

        Schema::table('lessons', function (Blueprint $table): void {
            $table->dropSoftDeletes();
        });

        Schema::table('projects', function (Blueprint $table): void {
            $table->dropSoftDeletes();
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropSoftDeletes();
        });
    }
};
