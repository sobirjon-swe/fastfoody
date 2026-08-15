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
        Schema::table('order_items', function (Blueprint $table) {
            // Oshxona shu qatordagi taom tugaganini belgilagan vaqt.
            $table->dateTime('out_of_stock_at')->nullable()->after('prep_minutes');
        });

        Schema::table('orders', function (Blueprint $table) {
            // Pul qaytarilgan vaqt (MVP'da simulyatsiya).
            $table->dateTime('refunded_at')->nullable()->after('paid_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn('out_of_stock_at');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('refunded_at');
        });
    }
};
