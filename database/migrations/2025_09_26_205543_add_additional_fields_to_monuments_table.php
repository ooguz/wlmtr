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
        Schema::table('monuments', function (Blueprint $table) {
            $table->text('aka')->nullable()->after('description_en'); // Aliases (also known as)
            $table->string('kulturenvanteri_id')->nullable()->after('aka'); // P11729 - Kültür Envanteri ID
            $table->string('commons_category')->nullable()->after('kulturenvanteri_id'); // P373 - Commons category
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('monuments', function (Blueprint $table) {
            $table->dropColumn(['aka', 'kulturenvanteri_id', 'commons_category']);
        });
    }
};
