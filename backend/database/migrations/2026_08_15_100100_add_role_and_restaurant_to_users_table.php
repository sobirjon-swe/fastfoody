<?php

use App\Enums\UserRole;
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
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 32)->nullable()->unique()->after('email');
            $table->string('role', 32)->default(UserRole::Customer->value)->index()->after('password');
            // Only restaurant staff belong to a restaurant; customers and super
            // admins keep this null.
            $table->foreignId('restaurant_id')->nullable()->after('role')
                ->constrained()->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('restaurant_id');
            $table->dropIndex(['role']);
            $table->dropColumn('role');
            $table->dropUnique(['phone']);
            $table->dropColumn('phone');
        });
    }
};
