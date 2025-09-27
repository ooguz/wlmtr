<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('monuments', function (Blueprint $table) {
            // Note: SQLite doesn't support conditional index creation via Schema builder.
            // We'll manually guard with PRAGMA index_list check.
        });

        $existing = collect(DB::select("PRAGMA index_list('monuments')"))->pluck('name');

        $createIndex = function (string $name, string $sql) use ($existing) {
            if (! $existing->contains($name)) {
                DB::statement($sql);
            }
        };

        $createIndex('monuments_lat_lng_idx', 'CREATE INDEX monuments_lat_lng_idx ON monuments (latitude, longitude)');
        $createIndex('monuments_province_index', 'CREATE INDEX monuments_province_index ON monuments (province)');
        $createIndex('monuments_city_index', 'CREATE INDEX monuments_city_index ON monuments (city)');
        $createIndex('monuments_district_index', 'CREATE INDEX monuments_district_index ON monuments (district)');
        $createIndex('monuments_has_photos_index', 'CREATE INDEX monuments_has_photos_index ON monuments (has_photos)');
        $createIndex('monuments_last_synced_at_index', 'CREATE INDEX monuments_last_synced_at_index ON monuments (last_synced_at)');
        $createIndex('monuments_photo_count_index', 'CREATE INDEX monuments_photo_count_index ON monuments (photo_count)');
    }

    public function down(): void
    {
        // Drop indexes if they exist
        $existing = collect(DB::select("PRAGMA index_list('monuments')"))->pluck('name');
        $dropIfExists = function (string $name) use ($existing) {
            if ($existing->contains($name)) {
                DB::statement('DROP INDEX '.$name);
            }
        };

        $dropIfExists('monuments_lat_lng_idx');
        $dropIfExists('monuments_province_index');
        $dropIfExists('monuments_city_index');
        $dropIfExists('monuments_district_index');
        $dropIfExists('monuments_has_photos_index');
        $dropIfExists('monuments_last_synced_at_index');
        $dropIfExists('monuments_photo_count_index');
    }
};
