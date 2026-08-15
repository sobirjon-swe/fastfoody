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

## API (0-bosqich)

Barcha javoblar JSON. Token `Authorization: Bearer <token>` sarlavhasida yuboriladi.

| Metod | Endpoint | Kirish huquqi | Tavsif |
|---|---|---|---|
| POST | `/api/auth/register` | ochiq | Mijoz hisobini yaratadi va token qaytaradi |
| POST | `/api/auth/login` | ochiq | Har qanday roldagi foydalanuvchini kiritadi |
| GET | `/api/auth/me` | token | Joriy foydalanuvchi + biriktirilgan oshxona |
| POST | `/api/auth/logout` | token | Faqat joriy qurilma tokenini oʻchiradi |

`register` va `login` daqiqasiga 10 martaga cheklangan (`throttle:10,1`).

## Testlar

```bash
cd backend && php artisan test      # 14 ta feature testi (auth + rol middleware)
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
- **`opens_at` / `closes_at`** oddiy `time` ustunlari; hozircha faqat maʼlumot sifatida
  koʻrsatiladi, buyurtma qabul qilish oynasi 3-bosqichda ishlatiladi.
- **shadcn/ui komponentlari** loyiha ichiga koʻchirilgan (`src/components/ui`), `components.json`
  ham saqlangan — yangi komponentni `npx shadcn@latest add <name>` bilan qoʻshish mumkin.

## Bosqichlar (roadmap)

- [x] **0-bosqich** — loyiha skeleti: Laravel API + React SPA, Sanctum autentifikatsiyasi,
      rollar, `restaurants` jadvali, rol boʻyicha himoyalangan sahifalar.
- [ ] **1-bosqich** — oshxona va menyu boshqaruvi (super_admin + restaurant_staff).
- [ ] **2-bosqich** — mijoz tomoni: menyu, savatcha, buyurtma berish.
- [ ] **3-bosqich** — tayyor boʻlish vaqtini hisoblash (miqdor + navbat).
- [ ] **4-bosqich** — buyurtma holatlarini boshqarish (oshxona paneli).
- [ ] **5-bosqich** — mahsulot tugagan holat va bekor qilish oqimi.
- [ ] Keyingi bosqichlar — Payme/Click integratsiyasi, real-time bildirishnoma.
