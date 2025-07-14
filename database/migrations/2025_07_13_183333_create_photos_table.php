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
        Schema::create('photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('monument_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            $table->string('commons_filename')->unique();
            $table->string('commons_url');
            $table->string('thumbnail_url')->nullable();
            $table->string('original_url')->nullable();
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->string('photographer')->nullable();
            $table->string('license')->nullable();
            $table->string('license_shortname')->nullable();
            $table->date('date_taken')->nullable();
            $table->string('camera_model')->nullable();
            $table->json('exif_data')->nullable();
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_uploaded_via_app')->default(false);
            $table->timestamp('uploaded_at')->nullable();
            $table->timestamps();
            
            // Indexes
            $table->index('commons_filename');
            $table->index('photographer');
            $table->index('date_taken');
            $table->index('is_featured');
            $table->index('is_uploaded_via_app');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('photos');
    }
};
