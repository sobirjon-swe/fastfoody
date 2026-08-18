<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Taom nomi va tavsifi oshxona kiritgan tilda `name`/`description` da
     * qoladi; boshqa tillar shu JSON ustunda saqlanadi:
     *
     *   {"ru": {"name": "Лаваш", "description": "...", "category": "..."}}
     *
     * Tarjima majburiy emas — boʻlmasa mijozga asl nom koʻrsatiladi, shuning
     * uchun xodimga qoʻshimcha yuk tushmaydi.
     *
     * Buyurtma qatorida ham nusxasi saqlanadi: buyurtma oʻz nusxasini
     * saqlaydi degan qoida tarjimaga ham tegishli, aks holda menyu
     * oʻzgargach eski buyurtma boshqa tilda koʻrinib qolardi.
     */
    public function up(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            $table->json('translations')->nullable()->after('category');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->json('translations')->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            $table->dropColumn('translations');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn('translations');
        });
    }
};
