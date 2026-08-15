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
        Schema::table('menu_items', function (Blueprint $table) {
            // Rasm `public` diskda saqlanadi, bazada faqat yoʻli turadi.
            $table->string('image_path')->nullable()->after('description');
            // Kategoriya alohida jadval emas, oddiy matn: menyu kichik va
            // oshxona uni erkin nomlaydi ("Lavashlar", "Ichimliklar").
            $table->string('category', 60)->nullable()->after('image_path');
            $table->index(['restaurant_id', 'category']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            $table->dropIndex(['restaurant_id', 'category']);
            $table->dropColumn(['image_path', 'category']);
        });
    }
};
