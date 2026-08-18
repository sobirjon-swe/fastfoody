<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Taom modifikatorlari (9-bosqich).
     *
     * Guruh — bu bitta savol («Oʻlcham», «Sous»), variant — javob («Katta»,
     * «Smetana»). Guruh majburiy yoki ixtiyoriy, bitta yoki bir nechta
     * tanlovli boʻlishi `min_select`/`max_select` bilan aniqlanadi:
     *
     *   min=1, max=1  → majburiy, bittasi (oʻlcham)
     *   min=0, max=1  → ixtiyoriy, bittasi (sous)
     *   min=0, max=null → ixtiyoriy, xohlagancha (qoʻshimchalar)
     *
     * Har bir variant narxga (`price_delta`) va tayyorlash vaqtiga
     * (`prep_delta_minutes`) taʼsir qilishi mumkin; ikkalasi ham manfiy
     * boʻlmaydi — «mayonez kerak emas» narxni kamaytirmaydi, shunchaki
     * qoʻshmaydi.
     */
    public function up(): void
    {
        Schema::create('option_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('menu_item_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->json('translations')->nullable();
            $table->unsignedTinyInteger('min_select')->default(0);
            $table->unsignedTinyInteger('max_select')->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->index(['menu_item_id', 'position']);
        });

        Schema::create('options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('option_group_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->json('translations')->nullable();
            $table->decimal('price_delta', 12, 2)->default(0);
            $table->unsignedSmallInteger('prep_delta_minutes')->default(0);
            $table->boolean('is_available')->default(true);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->index(['option_group_id', 'position']);
        });

        // Buyurtma oʻz nusxasini saqlaydi: variant keyin oʻchirilsa yoki
        // narxi oʻzgarsa ham eski buyurtma oʻzgarmaydi.
        Schema::create('order_item_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('option_id')->nullable()->constrained()->nullOnDelete();
            $table->string('group_name');
            $table->string('name');
            $table->json('translations')->nullable();
            $table->decimal('price_delta', 12, 2)->default(0);
            $table->unsignedSmallInteger('prep_delta_minutes')->default(0);
            $table->timestamps();

            $table->index('order_item_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_item_options');
        Schema::dropIfExists('options');
        Schema::dropIfExists('option_groups');
    }
};
