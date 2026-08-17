<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Foydalanuvchi tanlagan til. NULL boʻlsa til soʻrovdagi `Accept-Language`
     * sarlavhasidan olinadi — mijoz hech narsa tanlamasa ham oʻz tilida
     * koʻradi.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('locale', 10)->nullable()->after('phone');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('locale');
        });
    }
};
