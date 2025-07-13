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
        Schema::create('monuments', function (Blueprint $table) {
            $table->id();
            $table->string('wikidata_id')->unique()->index();
            $table->string('name_tr')->nullable();
            $table->string('name_en')->nullable();
            $table->text('description_tr')->nullable();
            $table->text('description_en')->nullable();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('district')->nullable();
            $table->string('province')->nullable();
            $table->string('country')->default('Turkey');
            $table->string('heritage_status')->nullable();
            $table->string('construction_date')->nullable();
            $table->string('architect')->nullable();
            $table->string('style')->nullable();
            $table->string('material')->nullable();
            $table->string('wikidata_url')->nullable();
            $table->string('wikipedia_url')->nullable();
            $table->string('commons_url')->nullable();
            $table->boolean('has_photos')->default(false);
            $table->integer('photo_count')->default(0);
            $table->json('properties')->nullable(); // Store additional Wikidata properties
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
            
            // Indexes for better performance
            $table->index(['latitude', 'longitude']);
            $table->index('has_photos');
            $table->index('heritage_status');
            $table->index('province');
            $table->index('city');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('monuments');
    }
};
