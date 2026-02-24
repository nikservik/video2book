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
        Schema::table('folders', function (Blueprint $table): void {
            $table->boolean('hidden')->default(false)->after('name');
            $table->json('visible_for')->default('[]')->after('hidden');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('folders', function (Blueprint $table): void {
            $table->dropColumn(['hidden', 'visible_for']);
        });
    }
};
