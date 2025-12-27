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
        Schema::table('step_versions', function (Blueprint $table): void {
            $table->foreignId('input_step_id')
                ->nullable()
                ->after('step_id')
                ->constrained('steps')
                ->nullOnDelete();

            $table->enum('status_new', ['draft', 'active', 'disabled'])
                ->default('draft')
                ->after('settings');
        });

        DB::table('step_versions')->update([
            'status_new' => DB::raw("CASE WHEN status = 'disabled' THEN 'disabled' ELSE 'active' END"),
        ]);

        Schema::table('step_versions', function (Blueprint $table): void {
            $table->dropColumn('status');
        });

        Schema::table('step_versions', function (Blueprint $table): void {
            $table->renameColumn('status_new', 'status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('step_versions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('input_step_id');

            $table->enum('status_old', ['active', 'disabled'])
                ->default('active')
                ->after('settings');
        });

        DB::table('step_versions')->update([
            'status_old' => DB::raw("CASE WHEN status = 'disabled' THEN 'disabled' ELSE 'active' END"),
        ]);

        Schema::table('step_versions', function (Blueprint $table): void {
            $table->dropColumn('status');
        });

        Schema::table('step_versions', function (Blueprint $table): void {
            $table->renameColumn('status_old', 'status');
        });
    }
};
