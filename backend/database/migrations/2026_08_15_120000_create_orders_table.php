<?php

use App\Enums\OrderStatus;
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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('restaurant_id')->constrained()->restrictOnDelete();
            $table->string('status', 40)->default(OrderStatus::Pending->value);
            $table->decimal('total_price', 12, 2);
            // Buyurtmaning oʻz tayyorlanish vaqti (navbatsiz). 3-bosqichda
            // navbat shu qiymat ustiga qoʻshiladi va ready_at toʻldiriladi.
            $table->unsignedInteger('prep_minutes');
            $table->dateTime('ready_at')->nullable();
            $table->dateTime('paid_at')->nullable();
            $table->timestamps();

            $table->index(['restaurant_id', 'status']);
            $table->index(['customer_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
