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
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'wikimedia_access_token',
                'wikimedia_refresh_token',
                'wikimedia_token_expires_at',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('wikimedia_access_token')->nullable();
            $table->text('wikimedia_refresh_token')->nullable();
            $table->timestamp('wikimedia_token_expires_at')->nullable();
        });
    }
};
