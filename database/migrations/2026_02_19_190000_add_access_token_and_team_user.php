<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('access_token', 64)->nullable()->unique();
        });

        $email = (string) config('simple_auth.email', 'team@local');

        $exists = DB::table('users')
            ->where('email', $email)
            ->exists();

        if ($exists) {
            return;
        }

        DB::table('users')->insert([
            'name' => 'Team',
            'email' => $email,
            'email_verified_at' => now(),
            'password' => Hash::make(Str::random(64)),
            'remember_token' => Str::random(10),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        $email = (string) config('simple_auth.email', 'team@local');

        DB::table('users')
            ->where('email', $email)
            ->delete();

        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique('users_access_token_unique');
            $table->dropColumn('access_token');
        });
    }
};
