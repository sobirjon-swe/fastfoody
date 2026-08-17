<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Telegram Mini App orqali kirgan foydalanuvchida email ham, parol ham
     * boʻlmasligi mumkin — Telegram bizga faqat oʻz identifikatorini beradi.
     * Shuning uchun ikkala ustun ham nullable qilinadi; email unikalligi
     * saqlanadi (NULL qiymatlar unikallikni buzmaydi).
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('telegram_id')->nullable()->unique()->after('id');
            $table->string('telegram_username')->nullable()->after('telegram_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
            $table->string('password')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['telegram_id', 'telegram_username']);
        });
    }
};
