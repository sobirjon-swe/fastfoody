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

**Buyurtmalar taxtasi — restaurant_staff (4-bosqich)**

| Metod | Endpoint | Tavsif |
|---|---|---|
| GET | `/api/staff/orders` | `?page=` (30 tadan, `meta` bilan). Ish taxtasi: toʻlangan, tayyorlanayotgan va tayyor buyurtmalar (eng eskisi birinchi); `?status=` bilan istalgan holat |
| GET | `/api/staff/orders/{id}` | Bitta buyurtma: tarkibi, mijoz ismi va telefoni |
| PATCH | `/api/staff/orders/{id}` | Holatni bir qadam oldinga surish (`{"status": "tayyorlanmoqda"}`) |
| POST | `/api/staff/orders/{id}/out-of-stock` | «Mahsulot tugadi»: `order_item_id` + ixtiyoriy `mark_menu_item_unavailable` |

Javobdagi `next_statuses` — shu buyurtma uchun ruxsat etilgan keyingi qadamlar; interfeys
tugmalarni shundan chizadi, qoidalar esa faqat serverda yashaydi.

Taom qaysi oshxonaga tegishli ekani **soʻrovdan olinmaydi** — u har doim kirgan xodimning
oshxonasi. Boshqa oshxona taomiga murojaat qilinsa 403 emas, **404** qaytadi: API uning
mavjudligini ham tasdiqlamaydi.

**Mijoz — koʻrish va buyurtma (2-bosqich)**

| Metod | Endpoint | Kirish huquqi | Tavsif |
|---|---|---|---|
| GET | `/api/restaurants` | ochiq | Faqat faol oshxonalar; `?q=` bilan qidiruv; javobda `is_open_now` |
| GET | `/api/restaurants/{id}` | ochiq | Oshxona + faqat mavjud taomlari (nofaol boʻlsa 404) |
| POST | `/api/orders/estimate` | mijoz | **Toʻlovdan oldin** tayyor boʻlish vaqti; hech narsa saqlanmaydi |
| POST | `/api/orders` | mijoz | Savatchadan buyurtma: `restaurant_id` + `items[{menu_item_id, quantity}]` |
| GET | `/api/orders` | mijoz | Faqat oʻz buyurtmalari; `?page=` (10 tadan, `meta` bilan) |
| GET | `/api/orders/{id}` | mijoz | Tarkibi bilan (begonasi 404) |
| POST | `/api/orders/{id}/pay` | mijoz | Toʻlov **simulyatsiyasi**; qayta toʻlashga 409 |
| POST | `/api/orders/{id}/replace-item` | mijoz | Tugagan taomni menyudagi boshqasiga almashtirish |
| POST | `/api/orders/{id}/cancel` | mijoz | Bekor qilish va pul qaytarish (simulyatsiya) |

Narx va tayyorlash vaqti **savatchadan olinmaydi** — server menyudan oʻqiydi, shuning uchun
mijoz yuborgan `unit_price` eʼtiborga olinmaydi. Butun savatcha bitta tranzaksiyada
yoziladi: bitta taom tugagan boʻlsa, buyurtma umuman yaratilmaydi.

## Tayyor boʻlish vaqti (3-bosqich)

Loyihaning oʻzagi. Vaqt ikki omildan yigʻiladi:

1. **Buyurtmaning oʻz vaqti** — miqdorga bogʻliq: `base_prep_minutes + extra_prep_minutes ×
   (miqdor − 1)`, savatchadagi hamma taom uchun yigʻiladi (`orders.prep_minutes`).
2. **Oshxona navbati** — shu oshxonada hozir tayyorlanayotgan (`tolov_qilindi` yoki
   `tayyorlanmoqda`) buyurtmalarning eng kechi qachon tugashi.

```
tayyor_boʻladi = max(hozir, navbatdagi oxirgi buyurtma tugash vaqti) + buyurtmaning oʻz vaqti
```

- Vaqt **toʻlovdan oldin** koʻrsatiladi (`POST /orders/estimate`), shunda mijoz «bu menga mos
  keladimi» deb oʻzi qaror qiladi.
- **Faqat toʻlangan buyurtma navbatda joy egallaydi** — tashlab ketilgan savatcha boshqalarning
  taomini kechiktirmaydi. Shu sababli `ready_at` toʻlov paytida qatʼiylashadi; toʻlanmagan
  buyurtmada esa har safar qayta hisoblanadigan `estimated_ready_at` koʻrsatiladi.
- Buyurtma «tayyor» deb belgilansa navbatdan chiqadi va keyingi mijozlarga koʻrsatiladigan vaqt
  oʻz-oʻzidan qisqaradi (baho har doim joriy navbatdan hisoblanadi).
- Navbat kechikkan boʻlsa ham vaqt oʻtmishda koʻrsatilmaydi (`max(hozir, ...)`).
- **Masʼuliyat qoidasi (TZ 5-bandi):** tizim mijozning haqiqiy kelish vaqtini kuzatmaydi. Mijoz
  kech qolsa yoki kelmasa — bu uning masʼuliyati.

**Buyurtma holatlari**

```
kutilmoqda → tolov_qilindi → tayyorlanmoqda → tayyor → olib_ketildi
```

Qoʻshimcha: `bekor_qilindi_mahsulot_yoq`, `muddati_otdi`.

Oʻtish qoidalari bitta joyda — `OrderStatus::nextForStaff()`:

| Kim | Oʻtish |
|---|---|
| Mijoz | `kutilmoqda → tolov_qilindi` (toʻlov) |
| Oshxona | `tolov_qilindi → tayyorlanmoqda → tayyor → olib_ketildi` |
| Oshxona | `tayyor → muddati_otdi` (mijoz kelmadi) |
| Tizim | `kutilmoqda → muddati_otdi`, `tayyor → muddati_otdi` (vaqt oʻtib ketdi) |

Zanjir faqat **oldinga** yuradi: sakrash ham, ortga qaytish ham 422 bilan rad etiladi.
Toʻlanmagan buyurtmani oshxona qoʻzgʻata olmaydi.

## Ish vaqti

Oshxonaning `opens_at`/`closes_at` maydonlari **majburiy**: yopiq oshxona buyurtmani ham,
tayyor boʻlish vaqti bahosini ham bermaydi (`422`). Mijoz roʻyxatida «Hozir yopiq» belgisi,
menyu sahifasida esa ogohlantirish koʻrinadi va «Buyurtma berish» tugmasi oʻchadi.

- Vaqtlar bazada **UTC**'da saqlanadi, ish vaqti esa mahalliy soatda yoziladi. Solishtirish
  `config/fastfoody.php` dagi `timezone` (sukut boʻyicha `Asia/Tashkent`) boʻyicha bajariladi.
- Yarim tundan oshadigan ish vaqti (`22:00–03:00`) toʻgʻri tushuniladi.
- Nofaol (`is_active: false`) oshxona soatdan qatʼi nazar yopiq.

## Osilib qolgan buyurtmalar

`php artisan orders:expire` toʻlanmay qolgan savatchalarni va olib ketilmagan taomlarni
`muddati_otdi` holatiga oʻtkazadi; jadval boʻyicha har 5 daqiqada ishlaydi
(server'da `php artisan schedule:work` yoki cron kerak). Oraliqlar `config/fastfoody.php`
da:

| Sozlama | Sukut | Maʼnosi |
|---|---|---|
| `expiry.unpaid_after_minutes` | 15 | Toʻlov qilinmagan buyurtma shuncha daqiqadan keyin yopiladi |
| `expiry.uncollected_after_minutes` | 30 | Tayyor boʻlib, olib ketilmagan buyurtma shuncha daqiqadan keyin yopiladi |

Tayyorlanayotgan yoki mijoz javobi kutilayotgan buyurtmaga tegilmaydi. Olib ketilmagan
buyurtma uchun **pul qaytarilmaydi** — TZ 5-bandidagi masʼuliyat qoidasi: tizim mijozning
kelishini kuzatmaydi. Oshxona xodimi tayyor buyurtmani «Kelmadi» tugmasi bilan qoʻlda ham
yopa oladi.

## Mahsulot tugaganda (5-bosqich)

Toʻlangan buyurtmadagi taom tugab qolsa:

1. **Oshxona** buyurtmadagi qaysi qator tugaganini belgilaydi (ixtiyoriy ravishda taomni
   menyudan ham «mavjud emas» qilib qoʻyadi). Buyurtma `mijoz_qarori_kutilmoqda` holatiga
   oʻtadi, `ready_at` boʻshatiladi va u **navbatdan chiqadi** — keyingi mijozlar bu
   buyurtma tufayli kutmaydi.
2. **Mijoz** ikkitadan birini tanlaydi:
   - **Almashtirish** — tugagan taom oʻrniga menyudan boshqasini oladi (miqdor oʻsha-oʻsha).
     Narx va tayyorlash vaqti yangi taomdan olinadi, buyurtma `tolov_qilindi` holatiga
     qaytadi va **navbatga qayta rejalashtiriladi**.
   - **Bekor qilish** — buyurtma `bekor_qilindi_mahsulot_yoq` boʻladi, `refunded_at`
     yoziladi (pul qaytarish ham MVP'da simulyatsiya).

Bloklangan buyurtma oshxona taxtasida koʻrinib turadi (`next_statuses` boʻsh) — xodim uning
mijoz javobini kutayotganini biladi, lekin uni oldinga sura olmaydi.

## Testlar

```bash
cd backend && php artisan test      # 117 ta test (auth, rollar, menyu, buyurtma, navbat, holatlar, muddat)
cd frontend && npm run build        # tsc + vite build
cd frontend && npm run lint
```

Testlar SQLite (`:memory:`) da ishlaydi, ishlab chiqarish va lokal muhit — MySQL.

## Interfeys xulq-atvori

- **Avtomatik yangilanish:** mijozning buyurtma sahifasi, buyurtmalar roʻyxati va oshxona
  taxtasi har 15 soniyada jimgina yangilanadi (`usePolling`). Holat oʻzgarsa mijozga
  bildirishnoma chiqadi — «mahsulot tugadi» xabari ham shu tariqa yetib boradi. Tugagan
  buyurtma soʻralmaydi.
- **Seans tugashi:** har qanday `401` javobida token tozalanadi va foydalanuvchi kirish
  sahifasiga qaytariladi (`api.ts` dagi yagona interceptor).
- **Sahifalash:** mijoz buyurtmalari 10 tadan, oshxona taxtasi 30 tadan. Tugmalar yuklanish
  paytida oʻchadi va eskirgan javob roʻyxatni bosib ketmaydi.

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
- **Buyurtma oʻz nusxasini saqlaydi** — `order_items` da taom nomi, narxi va tayyorlash
  vaqti buyurtma paytidagi holicha yoziladi, shuning uchun keyingi menyu oʻzgarishi eski
  buyurtmani oʻzgartirmaydi (taom oʻchirilsa `menu_item_id` null boʻladi, tarix qoladi).
- **Pul butun tiyinlarda hisoblanadi** (`App\Support\Money`) — float yaxlitlash xatosi ham,
  hamma joyda mavjud boʻlmagan `bcmath` kengaytmasiga bogʻliqlik ham yoʻq.
- **Toʻlov simulyatsiya** — `POST /orders/{id}/pay` faqat holatni oʻzgartiradi; Payme/Click
  integratsiyasi keyingi bosqichlarda.
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
- [x] **2-bosqich** — mijoz tomoni: oshxonalar roʻyxati, menyu, savatcha (miqdor tanlash),
      buyurtma berish, buyurtmalar roʻyxati va holati, toʻlov simulyatsiyasi.
- [x] **3-bosqich** — tayyor boʻlish vaqtini hisoblash: `KitchenQueue` xizmati, toʻlovdan
      oldingi baho, toʻlovda navbatga qoʻshilish, savatchada jonli koʻrsatish.
- [x] **4-bosqich** — oshxona paneli: buyurtmalar taxtasi (mijoz, tarkib, tayyor boʻlish
      vaqti), holatni oldinga surish, 15 soniyalik avtomatik yangilanish.
- [x] **5-bosqich** — mahsulot tugagan holat: oshxona bildiradi, mijoz almashtiradi yoki
      bekor qilib pulini qaytarib oladi; bloklangan buyurtma navbatni band qilmaydi.

- [x] **Qoʻshimcha** — osilib qolgan buyurtmalarni yopish (`orders:expire`), shu bilan
      `muddati_otdi` holati ham ishlaydi.

**MVP toʻliq bajarildi.**
- [ ] Keyingi bosqichlar — Payme/Click integratsiyasi, real-time bildirishnoma.
