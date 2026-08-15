<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed one account per role plus a demo restaurant, so every part of the
     * SPA can be signed into right after `php artisan migrate --seed`.
     * Password for all demo accounts: password
     */
    public function run(): void
    {
        $this->account('Super Admin', 'admin@fastfoody.uz', UserRole::SuperAdmin);

        $restaurant = Restaurant::firstOrCreate(
            ['name' => 'Oq Tepa Fastfood'],
            [
                'address' => 'Toshkent, Chilonzor 12',
                'phone' => '+998901234567',
                'opens_at' => '09:00:00',
                'closes_at' => '23:00:00',
                'is_active' => true,
            ],
        );

        $this->account('Oshxona Xodimi', 'staff@fastfoody.uz', UserRole::RestaurantStaff, $restaurant);
        $this->account('Mijoz Aliyev', 'customer@fastfoody.uz', UserRole::Customer);
    }

    private function account(string $name, string $email, UserRole $role, ?Restaurant $restaurant = null): User
    {
        $user = User::firstOrNew(['email' => $email]);

        $user->fill([
            'name' => $name,
            'password' => Hash::make('password'),
        ]);
        $user->role = $role;
        $user->restaurant_id = $restaurant?->id;
        $user->email_verified_at = now();
        $user->save();

        return $user;
    }
}
