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

    'expiry' => [
        // Toʻlov qilinmagan buyurtma shuncha daqiqadan keyin bekor boʻladi.
        'unpaid_after_minutes' => (int) env('FASTFOODY_UNPAID_EXPIRY_MINUTES', 15),

        // Tayyor boʻlgan, lekin olib ketilmagan buyurtma shuncha daqiqadan
        // keyin muddati oʻtgan hisoblanadi.
        'uncollected_after_minutes' => (int) env('FASTFOODY_UNCOLLECTED_EXPIRY_MINUTES', 30),
    ],

];
