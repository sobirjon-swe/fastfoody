# FastFoody

Mijoz fastfood doʻkoniga borishidan oldin ilova orqali buyurtma beradi va toʻlovni amalga
oshiradi. Tizim buyurtma tarkibi va oshxonaning joriy navbati asosida taom qachon tayyor
boʻlishini hisoblab beradi — mijoz yetib kelganda navbatda kutmaydi.

Platforma **koʻp oshxonali (multi-tenant)**: har bir doʻkon oʻz menyusi, buyurtmalari va
xodimlari bilan mustaqil ishlaydi.

## Papka tuzilishi

```
fastfoody/
├── backend/     → Laravel 13 REST API (PHP 8.3+, MySQL, Sanctum)
├── frontend/    → React 19 + Vite + Tailwind v4 + shadcn/ui SPA
└── README.md
```

Backend va frontend mustaqil loyihalar; faqat REST API orqali muloqot qiladi. Shuning uchun
kelajakda mobil ilova qoʻshilsa, xuddi shu API'dan foydalanadi.

## Ishga tushirish

### Backend (http://localhost:8000)

```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
# .env ichida DB_DATABASE / DB_USERNAME / DB_PASSWORD ni toʻldiring, keyin:
php artisan migrate --seed
php artisan serve
```

### Frontend (http://localhost:5173)

```bash
cd frontend
npm install
cp .env.example .env      # VITE_API_URL=http://localhost:8000/api
npm run dev
```

CORS backend'dagi `FRONTEND_URL` orqali boshqariladi (bir nechta origin vergul bilan).

### Demo hisoblar (seed'dan keyin)

| Rol | Email | Parol |
|---|---|---|
| Tizim egasi (super_admin) | admin@fastfoody.uz | password |
| Oshxona xodimi (restaurant_staff) | staff@fastfoody.uz | password |
| Mijoz (customer) | customer@fastfoody.uz | password |

## Rollar va multi-tenancy

| Rol | Vazifasi | Tenant |
|---|---|---|
| `customer` | Menyu koʻradi, buyurtma beradi, holatni kuzatadi | — |
| `restaurant_staff` | Faqat oʻz oshxonasi buyurtmalari va menyusi | `users.restaurant_id` |
| `super_admin` | Barcha oshxonalarni boshqaradi | — |

- Rol `users.role` ustunida saqlanadi va `App\Enums\UserRole` enum'iga cast qilinadi.
- `role` va `restaurant_id` **fillable emas** — ular hech qachon request'dan olinmaydi, faqat
  kod ichida (seeder, admin amallari) beriladi. Shu sababli roʻyxatdan oʻtishda `role` yuborib
  super admin boʻlib olish mumkin emas (test bilan qoplangan).
- Route'larni rol boʻyicha cheklash uchun `role` middleware alias'i tayyor:
  `Route::middleware(['auth:sanctum', 'role:restaurant_staff'])`.

## API

Barcha javoblar JSON. Token `Authorization: Bearer <token>` sarlavhasida yuboriladi.

**Autentifikatsiya (0-bosqich)**

| Metod | Endpoint | Kirish huquqi | Tavsif |
|---|---|---|---|
| POST | `/api/auth/register` | ochiq | Mijoz hisobini yaratadi va token qaytaradi |
| POST | `/api/auth/login` | ochiq | Har qanday roldagi foydalanuvchini kiritadi |
| GET | `/api/auth/me` | token | Joriy foydalanuvchi + biriktirilgan oshxona |
| POST | `/api/auth/logout` | token | Faqat joriy qurilma tokenini oʻchiradi |

`register` va `login` daqiqasiga 10 martaga cheklangan (`throttle:10,1`).

**Oshxona boshqaruvi — super_admin (1-bosqich)**

| Metod | Endpoint | Tavsif |
|---|---|---|
| GET | `/api/admin/restaurants` | Roʻyxat; `?q=`, `?status=active\|inactive`, `?page=` (15 tadan) |
| POST | `/api/admin/restaurants` | Yangi oshxona |
| GET | `/api/admin/restaurants/{id}` | Bitta oshxona |
| PATCH | `/api/admin/restaurants/{id}` | Tahrirlash; `is_active` bilan faollashtirish/oʻchirish |
| GET | `/api/admin/restaurants/{id}/staff` | Shu oshxona xodimlari |
| POST | `/api/admin/restaurants/{id}/staff` | Xodim hisobini yaratish (rol avtomatik `restaurant_staff`) |

Oshxona oʻchirilmaydi — `is_active: false` qilinadi, tarixi saqlanib qoladi.

**Menyu boshqaruvi — restaurant_staff (1-bosqich)**

| Metod | Endpoint | Tavsif |
|---|---|---|
| GET | `/api/staff/menu-items` | Faqat oʻz oshxonasi menyusi |
| POST | `/api/staff/menu-items` | Taom qoʻshish |
| GET | `/api/staff/menu-items/{id}` | Bitta taom |
| PATCH | `/api/staff/menu-items/{id}` | Tahrirlash, `is_available` bilan «tugadi» belgisi |
| DELETE | `/api/staff/menu-items/{id}` | Oʻchirish |

Taom qaysi oshxonaga tegishli ekani **soʻrovdan olinmaydi** — u har doim kirgan xodimning
oshxonasi. Boshqa oshxona taomiga murojaat qilinsa 403 emas, **404** qaytadi: API uning
mavjudligini ham tasdiqlamaydi.

## Testlar

```bash
cd backend && php artisan test      # 47 ta test (auth, rollar, oshxona va menyu boshqaruvi)
cd frontend && npm run build        # tsc + vite build
cd frontend && npm run lint
```

Testlar SQLite (`:memory:`) da ishlaydi, ishlab chiqarish va lokal muhit — MySQL.

## Qabul qilingan qarorlar

- **Kirish identifikatori — email.** TZ'da "telefon/email" deyilgan; hozircha email majburiy va
  unikal, telefon ixtiyoriy va unikal. Keyinchalik telefon orqali kirish qoʻshilsa, mavjud
  jadval oʻzgarmasdan kengaytiriladi.
- **Xodim va super admin oʻzi roʻyxatdan oʻta olmaydi** — bunday hisoblarni super admin yaratadi
  (1-bosqich).
- **`opens_at` / `closes_at`** bazada `time`, API'da esa doim `"HH:MM"` koʻrinishida
  (model accessor'i normallashtiradi); buyurtma qabul qilish oynasi sifatida 3-bosqichda
  ishlatiladi.
- **Menyuni faqat oshxona xodimi boshqaradi.** Super admin oshxona va xodim hisobini
  yaratadi, menyuni esa xodim toʻldiradi — shunda tenant chegarasi bitta joyda
  (`users.restaurant_id`) qoladi.
- **Narx `decimal(12,2)`** — API'da `"32000.00"` satri sifatida qaytadi (float yaxlitlash
  xatolarisiz), frontend uni `formatPrice` bilan `32 000 soʻm` koʻrinishida chizadi.
- **Taom oʻchirilmasin, «mavjud emas» qilinsin** — vaqtincha tugagan mahsulot uchun
  `is_available: false`; 5-bosqichdagi «mahsulot yoʻq» oqimi shunga tayanadi.
- **shadcn/ui komponentlari** loyiha ichiga koʻchirilgan (`src/components/ui`), `components.json`
  ham saqlangan — yangi komponentni `npx shadcn@latest add <name>` bilan qoʻshish mumkin.

## Bosqichlar (roadmap)

- [x] **0-bosqich** — loyiha skeleti: Laravel API + React SPA, Sanctum autentifikatsiyasi,
      rollar, `restaurants` jadvali, rol boʻyicha himoyalangan sahifalar.
- [x] **1-bosqich** — oshxona boshqaruvi (super_admin paneli: qidiruv, faollashtirish,
      xodim hisobi) va menyu boshqaruvi (`menu_items`, oshxona xodimi paneli:
      qoʻshish/tahrirlash/oʻchirish, «mavjud emas» belgisi, tayyorlash vaqti).
- [ ] **2-bosqich** — mijoz tomoni: menyu, savatcha, buyurtma berish.
- [ ] **3-bosqich** — tayyor boʻlish vaqtini hisoblash (miqdor + navbat).
- [ ] **4-bosqich** — buyurtma holatlarini boshqarish (oshxona paneli).
- [ ] **5-bosqich** — mahsulot tugagan holat va bekor qilish oqimi.
- [ ] Keyingi bosqichlar — Payme/Click integratsiyasi, real-time bildirishnoma.
