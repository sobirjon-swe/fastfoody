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
        Schema::create('menu_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('description', 500)->nullable();
            $table->decimal('price', 12, 2);
            // Tayyorlash vaqti: birinchi dona uchun bazaviy vaqt, har keyingi dona
            // uchun qoʻshimcha vaqt (3-bosqichdagi hisob shu ikki ustunga tayanadi).
            $table->unsignedSmallInteger('base_prep_minutes')->default(3);
            $table->unsignedSmallInteger('extra_prep_minutes')->default(1);
            $table->boolean('is_available')->default(true);
            $table->timestamps();

            $table->unique(['restaurant_id', 'name']);
            $table->index(['restaurant_id', 'is_available']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('menu_items');
    }
};
