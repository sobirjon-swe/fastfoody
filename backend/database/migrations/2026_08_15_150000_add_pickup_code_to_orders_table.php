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
        Schema::table('orders', function (Blueprint $table) {
            // Mijoz oshxonaga kelganda shu kodni aytadi. Toʻlov paytida
            // beriladi; bir oshxona ichida ayni damdagi buyurtmalar orasida
            // takrorlanmaydi.
            $table->string('pickup_code', 8)->nullable()->after('status');
            $table->index(['restaurant_id', 'pickup_code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['restaurant_id', 'pickup_code']);
            $table->dropColumn('pickup_code');
        });
    }
};
