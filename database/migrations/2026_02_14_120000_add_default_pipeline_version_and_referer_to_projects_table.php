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
            $table->foreignId('default_pipeline_version_id')
                ->nullable()
                ->after('tags')
                ->constrained('pipeline_versions')
                ->nullOnDelete();

            $table->string('referer')->nullable()->after('default_pipeline_version_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('default_pipeline_version_id');
            $table->dropColumn('referer');
        });
    }
};
