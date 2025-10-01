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
        // Check if we're using MySQL
        if (Schema::connection(config('queue.failed.database'))->getConnection()->getDriverName() === 'mysql') {
            Schema::connection(config('queue.failed.database'))->table('failed_jobs', function (Blueprint $table) {
                // Change uuid column to be nullable to avoid "Field doesn't have a default value" error
                $table->string('uuid')->nullable()->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::connection(config('queue.failed.database'))->getConnection()->getDriverName() === 'mysql') {
            Schema::connection(config('queue.failed.database'))->table('failed_jobs', function (Blueprint $table) {
                $table->string('uuid')->nullable(false)->change();
            });
        }
    }
};
