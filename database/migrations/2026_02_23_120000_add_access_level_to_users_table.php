<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->unsignedTinyInteger('access_level')
                ->default(User::ACCESS_LEVEL_USER)
                ->after('access_token')
                ->index();
        });

        DB::table('users')
            ->where('email', (string) config('simple_auth.email', 'team@local'))
            ->update(['access_level' => User::ACCESS_LEVEL_SUPERADMIN]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex('users_access_level_index');
            $table->dropColumn('access_level');
        });
    }
};
