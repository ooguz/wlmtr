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
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            Schema::table('photos', function (Blueprint $table) {
                $table->dropIndex('photos_photographer_index');
            });
        }

        Schema::table('photos', function (Blueprint $table) {
            $table->text('title')->nullable()->change();
            $table->text('photographer')->nullable()->change();
            $table->text('license')->nullable()->change();
            $table->text('license_shortname')->nullable()->change();
        });

        if ($driver === 'mysql') {
            DB::statement('CREATE INDEX photos_photographer_index ON photos (photographer(100))');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            Schema::table('photos', function (Blueprint $table) {
                $table->dropIndex('photos_photographer_index');
            });
        }

        Schema::table('photos', function (Blueprint $table) {
            $table->string('title')->nullable()->change();
            $table->string('photographer')->nullable()->change();
            $table->string('license')->nullable()->change();
            $table->string('license_shortname')->nullable()->change();
        });

        Schema::table('photos', function (Blueprint $table) {
            $table->index('photographer');
        });
    }
};
