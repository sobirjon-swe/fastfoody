<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Mahalliy vaqt mintaqasi
    |--------------------------------------------------------------------------
    |
    | Vaqtlar bazada UTC'da saqlanadi, lekin oshxonaning ish vaqti mahalliy
    | soatda yoziladi ("09:00" — Toshkent vaqti bilan). Ochiq-yopiqligini
    | tekshirishda shu mintaqa ishlatiladi.
    |
    */

    'timezone' => env('FASTFOODY_TIMEZONE', 'Asia/Tashkent'),

    /*
    |--------------------------------------------------------------------------
    | SPA manzili
    |--------------------------------------------------------------------------
    |
    | Parolni tiklash havolasi shu manzilga olib boradi. FRONTEND_URL bir
    | nechta origin boʻlsa, birinchisi olinadi.
    |
    */

    'frontend_url' => trim(explode(',', (string) env('FRONTEND_URL', 'http://localhost:5173'))[0]),

    /*
    |--------------------------------------------------------------------------
    | Buyurtma muddati
    |--------------------------------------------------------------------------
    |
    | Toʻlanmagan savatcha va olib ketilmagan taom abadiy osilib turmasligi
    | kerak. `orders:expire` buyrugʻi shu oraliqlardan oshganini `muddati_otdi`
    | holatiga oʻtkazadi.
    |
    | TZ 5-bandidagi masʼuliyat qoidasi: tizim mijozning kelishini kuzatmaydi,
    | shuning uchun olib ketilmagan buyurtma uchun pul qaytarilmaydi.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Telegram Mini App
    |--------------------------------------------------------------------------
    |
    | Telegram initData'ni imzo bilan birga `auth_date` ham yuboradi. Eski
    | initData qayta ishlatilmasligi uchun uning yoshi cheklanadi.
    |
    */

    'telegram' => [
        'max_auth_age_minutes' => (int) env('TELEGRAM_MAX_AUTH_AGE_MINUTES', 1440),
    ],

    'expiry' => [
        // Toʻlov qilinmagan buyurtma shuncha daqiqadan keyin bekor boʻladi.
        'unpaid_after_minutes' => (int) env('FASTFOODY_UNPAID_EXPIRY_MINUTES', 15),

        // Tayyor boʻlgan, lekin olib ketilmagan buyurtma shuncha daqiqadan
        // keyin muddati oʻtgan hisoblanadi.
        'uncollected_after_minutes' => (int) env('FASTFOODY_UNCOLLECTED_EXPIRY_MINUTES', 30),
    ],

];
