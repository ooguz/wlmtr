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
            $table->string('wikimedia_id')->nullable()->unique()->index();
            $table->string('wikimedia_username')->nullable()->unique()->index();
            $table->string('wikimedia_real_name')->nullable();
            $table->json('wikimedia_groups')->nullable();
            $table->json('wikimedia_rights')->nullable();
            $table->integer('wikimedia_edit_count')->default(0);
            $table->timestamp('wikimedia_registration_date')->nullable();
            $table->string('wikimedia_access_token')->nullable();
            $table->string('wikimedia_refresh_token')->nullable();
            $table->timestamp('wikimedia_token_expires_at')->nullable();
            $table->boolean('has_commons_edit_permission')->default(false);
            $table->timestamp('last_wikimedia_sync')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'wikimedia_id',
                'wikimedia_username',
                'wikimedia_real_name',
                'wikimedia_groups',
                'wikimedia_rights',
                'wikimedia_edit_count',
                'wikimedia_registration_date',
                'wikimedia_access_token',
                'wikimedia_refresh_token',
                'wikimedia_token_expires_at',
                'has_commons_edit_permission',
                'last_wikimedia_sync',
            ]);
        });
    }
};
