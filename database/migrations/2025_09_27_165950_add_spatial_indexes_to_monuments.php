<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
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

            return;
        }

        // MySQL / MariaDB path
        if (in_array($driver, ['mysql', 'mariadb'])) {
            $existing = collect(DB::select(
                "SELECT INDEX_NAME AS name FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'monuments'"
            ))->pluck('name');

            $createIndex = function (string $name, string $sql) use ($existing) {
                if (! $existing->contains($name)) {
                    DB::statement($sql);
                }
            };

            $createIndex('monuments_lat_lng_idx', 'CREATE INDEX monuments_lat_lng_idx ON `monuments` (`latitude`, `longitude`)');
            $createIndex('monuments_province_index', 'CREATE INDEX monuments_province_index ON `monuments` (`province`)');
            $createIndex('monuments_city_index', 'CREATE INDEX monuments_city_index ON `monuments` (`city`)');
            $createIndex('monuments_district_index', 'CREATE INDEX monuments_district_index ON `monuments` (`district`)');
            $createIndex('monuments_has_photos_index', 'CREATE INDEX monuments_has_photos_index ON `monuments` (`has_photos`)');
            $createIndex('monuments_last_synced_at_index', 'CREATE INDEX monuments_last_synced_at_index ON `monuments` (`last_synced_at`)');
            $createIndex('monuments_photo_count_index', 'CREATE INDEX monuments_photo_count_index ON `monuments` (`photo_count`)');

            return;
        }

        // Fallback: try Schema builder and ignore duplicate errors
        Schema::table('monuments', function (Blueprint $table) {
            try { $table->index(['latitude', 'longitude'], 'monuments_lat_lng_idx'); } catch (\Throwable $e) {}
            try { $table->index('province'); } catch (\Throwable $e) {}
            try { $table->index('city'); } catch (\Throwable $e) {}
            try { $table->index('district'); } catch (\Throwable $e) {}
            try { $table->index('has_photos'); } catch (\Throwable $e) {}
            try { $table->index('last_synced_at'); } catch (\Throwable $e) {}
            try { $table->index('photo_count'); } catch (\Throwable $e) {}
        });
    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            $existing = collect(DB::select("PRAGMA index_list('monuments')"))->pluck('name');
            $dropIfExists = function (string $name) use ($existing) {
                if ($existing->contains($name)) {
                    DB::statement('DROP INDEX ' . $name);
                }
            };

            $dropIfExists('monuments_lat_lng_idx');
            $dropIfExists('monuments_province_index');
            $dropIfExists('monuments_city_index');
            $dropIfExists('monuments_district_index');
            $dropIfExists('monuments_has_photos_index');
            $dropIfExists('monuments_last_synced_at_index');
            $dropIfExists('monuments_photo_count_index');

            return;
        }

        if (in_array($driver, ['mysql', 'mariadb'])) {
            $existing = collect(DB::select(
                "SELECT INDEX_NAME AS name FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'monuments'"
            ))->pluck('name');
            $dropIfExists = function (string $name) use ($existing) {
                if ($existing->contains($name)) {
                    DB::statement('DROP INDEX ' . $name . ' ON `monuments`');
                }
            };

            $dropIfExists('monuments_lat_lng_idx');
            $dropIfExists('monuments_province_index');
            $dropIfExists('monuments_city_index');
            $dropIfExists('monuments_district_index');
            $dropIfExists('monuments_has_photos_index');
            $dropIfExists('monuments_last_synced_at_index');
            $dropIfExists('monuments_photo_count_index');

            return;
        }

        // Fallback
        Schema::table('monuments', function (Blueprint $table) {
            try { $table->dropIndex('monuments_lat_lng_idx'); } catch (\Throwable $e) {}
            try { $table->dropIndex(['province']); } catch (\Throwable $e) {}
            try { $table->dropIndex(['city']); } catch (\Throwable $e) {}
            try { $table->dropIndex(['district']); } catch (\Throwable $e) {}
            try { $table->dropIndex(['has_photos']); } catch (\Throwable $e) {}
            try { $table->dropIndex(['last_synced_at']); } catch (\Throwable $e) {}
            try { $table->dropIndex(['photo_count']); } catch (\Throwable $e) {}
        });
    }
};
