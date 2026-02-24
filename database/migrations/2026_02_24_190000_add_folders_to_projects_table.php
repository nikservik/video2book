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
        Schema::create('folders', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        Schema::table('projects', function (Blueprint $table): void {
            $table->foreignId('folder_id')
                ->nullable()
                ->after('id')
                ->constrained('folders')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });

        $defaultFolderId = DB::table('folders')->insertGetId([
            'name' => 'Проекты',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('projects')
            ->whereNull('folder_id')
            ->update(['folder_id' => $defaultFolderId]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('folder_id');
        });

        Schema::dropIfExists('folders');
    }
};
