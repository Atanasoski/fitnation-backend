<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('password')->nullable()->change();
            $table->string('social_provider')->nullable()->after('password');
            $table->string('social_provider_id')->nullable()->after('social_provider');

            $table->unique(['social_provider', 'social_provider_id'], 'users_social_provider_unique');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_social_provider_unique');
            $table->dropColumn(['social_provider', 'social_provider_id']);
            $table->string('password')->nullable(false)->change();
        });
    }
};
