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
            $table->string('name')->nullable()->after('step_id');
        });

        DB::table('step_versions')
            ->join('steps', 'step_versions.step_id', '=', 'steps.id')
            ->select('step_versions.id', 'steps.name')
            ->orderBy('step_versions.id')
            ->chunkById(200, function ($rows): void {
                foreach ($rows as $row) {
                    DB::table('step_versions')
                        ->where('id', $row->id)
                        ->update(['name' => $row->name]);
                }
            }, 'step_versions.id');

        Schema::table('steps', function (Blueprint $table): void {
            $table->dropColumn('name');
        });

        // Column left nullable to keep compatibility across drivers.
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('steps', function (Blueprint $table): void {
            $table->string('name')->nullable();
        });

        DB::table('steps')
            ->join('step_versions', 'steps.current_version_id', '=', 'step_versions.id')
            ->select('steps.id', 'step_versions.name')
            ->orderBy('steps.id')
            ->chunkById(200, function ($rows): void {
                foreach ($rows as $row) {
                    DB::table('steps')
                        ->where('id', $row->id)
                        ->update(['name' => $row->name]);
                }
            }, 'steps.id');

        Schema::table('step_versions', function (Blueprint $table): void {
            $table->dropColumn('name');
        });
    }
};
