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
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('wikidata_id')->unique()->index();
            $table->string('name_tr');
            $table->string('name_en')->nullable();
            $table->text('description_tr')->nullable();
            $table->text('description_en')->nullable();
            $table->string('parent_category_id')->nullable();
            $table->string('icon')->nullable();
            $table->string('color')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('monument_count')->default(0);
            $table->timestamps();
            
            // Indexes
            $table->index('parent_category_id');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
