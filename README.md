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
php artisan storage:link          # taom rasmlari uchun (bir marta)
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

Seed ikkita oshxona yaratadi: **Oq Tepa Fastfood** (sutka boʻyi, demo menyu va ikkita
buyurtma bilan) va **Chorsu Lavash** (09:00–23:00) — ikkinchisi yopiq oshxona mijozga qanday
koʻrinishini tekshirish uchun.

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
| PATCH | `/api/auth/profile` | token | Ism, email va telefonni yangilash (rol va oshxona oʻzgarmaydi) |
| PUT | `/api/auth/password` | token | Parolni almashtirish; joriy paroldan tashqari hamma token oʻchadi |
| POST | `/api/auth/forgot-password` | ochiq | Emailga tiklash havolasi (SPA'dagi `/parolni-tiklash`) |
| POST | `/api/auth/reset-password` | ochiq | `token` + yangi parol; barcha seanslar yopiladi |
| POST | `/api/auth/telegram` | ochiq | Telegram Mini App'dan kirish: `init_data` imzosi tekshiriladi, token qaytadi |
| POST | `/api/auth/logout` | token | Faqat joriy qurilma tokenini oʻchiradi |

`register` va `login` daqiqasiga 10 martaga, parol tiklash esa 5 martaga cheklangan.
`forgot-password` hisob bor-yoʻqligidan qatʼi nazar **bir xil javob** qaytaradi — shu orqali
qaysi emaillar roʻyxatdan oʻtganini aniqlab boʻlmaydi.

**Oshxona boshqaruvi — super_admin (1-bosqich)**

| Metod | Endpoint | Tavsif |
|---|---|---|
| GET | `/api/admin/restaurants` | Roʻyxat; `?q=`, `?status=active\|inactive`, `?page=` (15 tadan) |
| POST | `/api/admin/restaurants` | Yangi oshxona |
| GET | `/api/admin/restaurants/{id}` | Bitta oshxona |
| PATCH | `/api/admin/restaurants/{id}` | Tahrirlash; `is_active` bilan faollashtirish/oʻchirish |
| GET | `/api/admin/restaurants/{id}/staff` | Shu oshxona xodimlari |
| POST | `/api/admin/restaurants/{id}/staff` | Xodim hisobini yaratish (rol avtomatik `restaurant_staff`) |
| DELETE | `/api/admin/restaurants/{id}/staff/{staff}` | Xodim hisobini oʻchirib qoʻyish (tokenlari bekor qilinadi) |
| POST | `/api/admin/restaurants/{id}/staff/{staff}/restore` | Oʻchirilgan xodimni qaytarish |
| GET | `/api/admin/statistics` | Butun tizim + har bir oshxona kesimida koʻrsatkichlar |

Oshxona oʻchirilmaydi — `is_active: false` qilinadi, tarixi saqlanib qoladi. Xodim hisobi ham
oʻchirilmaydi: `deactivated_at` qoʻyiladi, tokenlari bekor qilinadi va u tizimga kira olmaydi,
lekin uning buyurtmalardagi izi saqlanadi.

**Menyu boshqaruvi — restaurant_staff (1-bosqich)**

| Metod | Endpoint | Tavsif |
|---|---|---|
| GET | `/api/staff/menu-items` | Faqat oʻz oshxonasi menyusi |
| POST | `/api/staff/menu-items` | Taom qoʻshish |
| GET | `/api/staff/menu-items/{id}` | Bitta taom |
| PATCH | `/api/staff/menu-items/{id}` | Tahrirlash, `is_available` bilan «tugadi» belgisi |
| DELETE | `/api/staff/menu-items/{id}` | Oʻchirish |
| POST | `/api/staff/menu-items/{id}/image` | Rasm yuklash (`multipart/form-data`, `image`, ≤2 MB) |
| DELETE | `/api/staff/menu-items/{id}/image` | Rasmni olib tashlash |
| GET | `/api/staff/statistics` | Faqat oʻz oshxonasi koʻrsatkichlari |

Taomni **kategoriyaga** ajratish mumkin (`category`, masalan «Ichimliklar»): mijoz menyusi
kategoriyalar boʻyicha guruhlanadi, xodim panelida esa kategoriya boʻyicha filtr bor.
Rasmlar `storage/app/public/menu-items` ichida saqlanadi, shuning uchun bir marta
`php artisan storage:link` bajarish kerak; eskisi yangisi bilan almashtirilganda oʻchiriladi.

**Buyurtmalar taxtasi — restaurant_staff (4-bosqich)**

| Metod | Endpoint | Tavsif |
|---|---|---|
| GET | `/api/staff/orders` | `?page=` (30 tadan, `meta` bilan). Ish taxtasi: toʻlangan, tayyorlanayotgan va tayyor buyurtmalar (eng eskisi birinchi); `?status=` bilan istalgan holat; `?code=` bilan olib ketish kodi boʻyicha qidiruv |
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
yoziladi: bitta taom tugagan boʻlsa, buyurtma umuman yaratilmaydi. Savatchada koʻpi bilan
50 xil taom, har biridan 50 tagacha boʻlishi mumkin — bitta soʻrov bilan oshxonaning butun
navbatini band qilib boʻlmaydi.

## Olib ketish kodi

Toʻlov amalga oshgan zahoti buyurtmaga **4 xonali kod** beriladi (`orders.pickup_code`).
Mijoz uni buyurtma sahifasida koʻradi, xodim esa taxtadagi qidiruv maydoniga kiritib
buyurtmani darrov topadi — «familiyangiz nima edi?» degan savolga hojat qolmaydi.

Kod bitta oshxonaning **ochiq** buyurtmalari orasida takrorlanmaydi; yopilgan buyurtmalarning
kodlari qayta ishlatilaveradi, shuning uchun 4 xona umrbod yetadi. Boʻsh kod topilmasa
(nazariy holat) 6 belgili zaxira kod beriladi.

## Modifikatorlar (9-bosqich)

Taomga savol-javob shaklidagi tanlovlar qoʻshiladi: **guruh** — savol («Sous», «Oʻlcham»),
**variant** — javob («Smetana», «Katta»). Guruh turi ikki son bilan aniqlanadi:

| `min_select` | `max_select` | Maʼnosi |
|---|---|---|
| 1 | 1 | Majburiy, bittasi — oʻlcham |
| 0 | 1 | Ixtiyoriy, bittasi — sous |
| 0 | — | Ixtiyoriy, xohlagancha — qoʻshimchalar |

- **Narx har bir dona uchun** qoʻshiladi (3 ta gamburgerga pishloq — uch marta pul),
  **tayyorlash vaqti esa qatorga bir marta**: sous butun partiyaga birdan qoʻshiladi, uni har
  donaga koʻpaytirish navbatni asossiz choʻzardi.
- Narx va vaqt qoʻshimchasi **manfiy boʻlmaydi** — «mayonez kerak emas» narxni kamaytirmaydi.
- Tanlov ham narx kabi **serverda tekshiriladi**: mijoz faqat `option_ids` yuboradi, qolgani
  menyudan oʻqiladi. Begona taomning varianti, mavjud boʻlmagan variant, majburiy guruhga
  javob berilmagani yoki bitta tanlovli guruhga ikkita javob — hammasi `422`.
- Guruh va variant nomlari ham **tarjima qilinadi**, buyurtma esa oʻz nusxasini saqlaydi
  (`order_item_options`): variant keyin oʻchirilsa yoki narxi oshsa ham eski chek oʻzgarmaydi.

| Metod | Endpoint | Tavsif |
|---|---|---|
| PUT | `/api/staff/menu-items/{id}/options` | Guruhlarning yakuniy holati: roʻyxatda yoʻq guruh yoki variant oʻchiriladi |

Interfeysda: xodim panelida taom yonidagi roʻyxat tugmasi guruhlarni tahrirlash oynasini
ochadi — «Majburiy» va «Koʻp tanlov» tugmalari `min_select`/`max_select` ni yashiradi, chunki
xodimga son emas, maʼno kerak. Mijoz tomonida modifikatorli taomga «+» bosilganda tanlov
oynasi ochiladi: bitta tanlovli guruh radio, koʻp tanlovli guruh belgilash koʻrinishida
chiziladi, tugmada esa bitta dona narxi jonli koʻrinadi. Bitta taom har xil tanlovlar bilan
savatchada alohida qator boʻlib turadi.

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

## Tillar (8-bosqich)

Interfeys toʻrt tilda: **oʻzbekcha lotin** (asosiy), **oʻzbekcha kirill**, **ruscha** va
**inglizcha**.

Til quyidagi tartibda tanlanadi:

1. Foydalanuvchi profilida saqlangan til (`users.locale`, `PATCH /api/auth/profile`);
2. soʻrovdagi `Accept-Language` sarlavhasi;
3. sukut boʻyicha `APP_LOCALE` (oʻzbekcha lotin).

Telegram orqali birinchi marta kirgan foydalanuvchiga `language_code` dan til qoʻyiladi
(`en-GB` → `en`), keyin uni profilidan oʻzgartira oladi. Bot xabarnomalari **mijozning**
tilida yoziladi — holatni oʻzgartirgan xodimning tilida emas.

Frontend tarjimasi `frontend/src/i18n/` da: `uz.ts` (kalitlar), `uz-cyrl.ts`, `ru.ts`, `en.ts`.
Boshqa tillar `Record<TranslationKey, string>` sifatida yozilgani uchun **tarjima tushib qolsa
loyiha kompilyatsiya boʻlmaydi**. Til almashtirgich sarlavhada va profil sahifasida; kirmagan
foydalanuvchi uchun kirish sahifasida ham bor. Valyuta nomi ham tarjima qilinadi
(«32 000 soʻm» / «32 000 сум»), raqam formati esa hamma tilda bir xil qoladi.

Backend tarjima kalitlari — oʻzbekcha lotin jumlalarning oʻzi: `lang/uz.json` (ayni jumlalar),
`lang/uz_Cyrl.json`, `lang/ru.json`, `lang/en.json`, hamda
`lang/{uz_Cyrl,ru}/{auth,validation}.php` va `lang/en/validation.php`. `lang/uz.json` boʻsh koʻrinsa ham **kerak**: usiz `uz` tili zaxira
tilga (`en`) tushib ketadi va oʻzbek foydalanuvchi inglizcha xabar koʻradi.

### Menyu tarjimasi

Taom nomi, tavsifi va kategoriyasi ham tarjima qilinadi — aks holda interfeysni tarjima
qilishning maʼnosi qolmaydi: mijoz taom nimaligini tushunmasa, ekran qaysi tilda ekani muhim
emas.

- Asosiy matn oshxona kiritgan tilda `menu_items.name` / `description` / `category` da qoladi;
  boshqa tillar `menu_items.translations` JSON ustunida saqlanadi.
- **Tarjima majburiy emas.** Xodim bitta tilda ishlayversa ham boʻladi: tarjima yoʻq boʻlsa
  mijozga asl nom koʻrsatiladi, boʻsh joy emas. Xodim panelidagi «Boshqa tillarda» boʻlimi
  yigʻilgan holda turadi, shuning uchun kundalik ish soddaligicha qoladi.
- **Xodim doim asl matnni tahrirlaydi.** Xodim paneli alohida `StaffMenuItemResource` dan
  foydalanadi — u matnni tarjima qilmaydi. Aks holda ruscha ishlayotgan xodim taomni
  saqlaganda tarjima asl nom oʻrniga yozilib ketardi (test bilan qoplangan).
- **Buyurtma oʻz nusxasini saqlaydi** — tarjimaga ham tegishli: `order_items.translations`
  buyurtma paytidagi holicha yoziladi, shuning uchun menyu keyin oʻzgarsa ham mijoz
  buyurtmasini oʻsha nom bilan koʻradi.
- Asosiy tilni tarjimalar orasiga yozishga urinish `422` bilan rad etiladi — u `name`
  maydonida turadi, ikki joyda emas.

## Telegram Mini App (7-bosqich)

Mijoz tomonini Telegram ichida ochish uchun asos tayyor. Mini App sahifasi Telegram'dan
`initData` satrini oladi va uni `POST /api/auth/telegram` ga yuboradi; server imzoni bot
tokeni bilan tekshiradi va odatdagi Sanctum tokenini qaytaradi — undan keyingi hamma soʻrov
oʻzgarishsiz ishlaydi.

- Imzo Telegram hujjatidagi algoritm boʻyicha tekshiriladi: **faqat `hash`** chiqarib
  tashlanadi, qolgan juftliklar alifbo boʻyicha tartiblanib `\n` bilan ulanadi, kalit sifatida
  `HMAC-SHA256("WebAppData", bot_token)` ishlatiladi.
- **Bot API 8.0 dagi `signature` maydoni data-check-string ichida qoladi.** U uchinchi tomon
  Ed25519 tekshiruvi uchun moʻljallangan, lekin Telegram `hash` ni oʻsha maydon bilan birga
  hisoblaydi. Uni chiqarib tashlash imzoni zamonaviy Telegram ilovalarining hech birida mos
  kelmaydigan qiladi — yaʼni Mini App hech kimda ishlamaydi. Shu holat alohida test bilan
  qoplangan (`signature` bor initData qabul qilinadi, uning qiymati oʻzgartirilsa rad etiladi).
- `auth_date` yoshi cheklangan (`TELEGRAM_MAX_AUTH_AGE_MINUTES`, sukut 24 soat) — yopilgan
  oynadan qolgan eski `initData` qayta ishlatilmaydi.
- Hisob birinchi kirishda oʻzi ochiladi va **doim `customer`** boʻladi; mavjud hisob topilsa
  uning roli va oshxonasi saqlanadi. Rol hech qachon Telegram maʼlumotidan olinmaydi.
- Telegram orqali ochilgan hisobda email ham, parol ham boʻlmasligi mumkin (`users.email` va
  `users.password` endi nullable). Bunday hisobga email+parol bilan kirib boʻlmaydi; foydalanuvchi
  keyinchalik profilidan email qoʻshsa, parolni tiklash orqali oddiy kirishni ham yoqadi.
- `TELEGRAM_BOT_TOKEN` sozlanmagan boʻlsa endpoint `503` qaytaradi — mijozga imzo xatosi
  koʻrsatilmaydi.

Xodim va tizim egasi paneli Mini App'ga koʻchirilmaydi: ular kun boʻyi katta ekranda
ishlaydi, ikkalasi ham xuddi shu API'ga ulanaveradi.

### Interfeys Telegram ichida

Bitta SPA ikkala muhitda ham ishlaydi — `src/lib/telegram.ts` dagi hamma funksiya Telegram
boʻlmasa jimgina hech narsa qilmaydi:

- **Kirish sahifasi koʻrsatilmaydi.** Mini App ochilishi bilan `initData` yuboriladi va
  foydalanuvchi oʻz ekraniga tushadi; imzo rad etilsa odatdagi kirish sahifasi chiqadi.
- **Mavzu.** Telegram qorongʻi rejimda boʻlsa ilova ham qorongʻi palitraga oʻtadi
  (`themeChanged` hodisasiga obuna boʻlinadi).
- **`MainButton`.** Savatchada taom paydo boʻlishi bilan «Buyurtma berish» Telegram'ning
  pastdagi asosiy tugmasiga chiqadi; brauzerda esa kartadagi tugma oʻz oʻrnida qoladi.
- **`BackButton`.** Menyu sahifasida Telegram sarlavhasidagi «orqaga» tugmasi ishlaydi.

`index.html` Telegram skriptini yuklaydi. Skript yuklanmasa ham (masalan tarmoq bloklagan
boʻlsa) ilova oddiy veb-ilova sifatida ishlayveradi.

### Bot xabarnomalari

Buyurtma holati oʻzgarganda Telegram orqali kirgan mijozga bot xabar yozadi — ilova yopiq
boʻlsa ham «buyurtmangiz tayyor» xabari telefoniga keladi:

| Holat | Xabar |
|---|---|
| `tolov_qilindi` | Olib ketish kodi va taxminiy tayyor boʻlish vaqti |
| `tayyor` | «Buyurtmangiz tayyor!» + kod |
| `mijoz_qarori_kutilmoqda` | Mahsulot tugadi, ilovada almashtiring yoki bekor qiling |
| `bekor_qilindi_mahsulot_yoq` | Bekor qilindi, toʻlov qaytariladi |
| `muddati_otdi` | Muddati oʻtgani uchun yopildi |

- Xabar **bitta joydan** — `OrderObserver` orqali — yuboriladi, shuning uchun holat qayerda
  oʻzgarishidan (mijoz toʻlovi, oshxona paneli, `orders:expire`) qatʼi nazar mijoz xabardor
  boʻladi. Shu sababli `orders:expire` ommaviy `update()` emas, buyurtmalarni bittalab
  saqlaydi: aks holda model hodisalari ishlamas edi.
- «Tayyorlanmoqda» kabi oraliq holatlar uchun xabar yuborilmaydi — telefon behuda
  chirillamasligi kerak.
- Xabar yuborilmasligi biznes jarayonini toʻxtatmaydi: nosozlik logga yoziladi, soʻrov esa
  muvaffaqiyatli tugaydi. Bot tokeni sozlanmagan boʻlsa hech narsa yuborilmaydi.
- Yuborish `SendTelegramMessage` job'i orqali ketadi, shuning uchun `QUEUE_CONNECTION` ni
  oʻzgartirish bilanoq xabarlar navbatga oʻtadi.

## Statistika

Oshxona xodimi oʻz taxtasida, super admin esa panelida bugungi va soʻnggi 7 kunlik
koʻrsatkichlarni koʻradi: buyurtmalar soni, tushum, oʻrtacha tayyorlash vaqti, bekor
qilingan va muddati oʻtgan buyurtmalar. Super admin roʻyxatida har bir oshxonaning bugungi
raqami alohida ustunda turadi.

Tushumga **faqat pul kelgan** buyurtmalar kiradi: toʻlangandan olib ketilgangacha boʻlgan
holatlar. Bekor qilingan (pul qaytarilgan) va muddati oʻtgan buyurtmalar tushumga
qoʻshilmaydi, lekin alohida sanaladi. Sanalar `config/fastfoody.php` dagi mahalliy
vaqt mintaqasi boʻyicha kesiladi, shuning uchun «bugun» Toshkent yarim tunidan boshlanadi.

## Testlar

```bash
cd backend  && php artisan test     # 210 ta test
cd backend  && ./vendor/bin/pint    # kod uslubi
cd frontend && npm run test         # 33 ta test (Vitest + Testing Library)
cd frontend && npm run lint         # oxlint
cd frontend && npm run build        # tsc + vite build
```

Backend testlari: auth va rollar, tenant chegarasi, menyu, savatcha va buyurtma, navbat
hisobi, holat oʻtishlari, ish vaqti, muddati oʻtganlar, olib ketish kodi, rasm yuklash,
profil, parol tiklash, statistika va Telegram imzosi. SQLite (`:memory:`) da ishlaydi — ishlab chiqarish va
lokal muhit esa MySQL.

Har bir push va pull request'da GitHub Actions (`.github/workflows/ci.yml`) ikkala loyihani
ham tekshiradi: backend uchun Pint + PHPUnit, frontend uchun lint + test + build.

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

**MVP toʻliq bajarildi.** Undan keyin qoʻshilganlar:

- [x] Ish vaqti tekshiruvi, jonli yangilanish, `401` da seansni tozalash, sahifalash.
- [x] CI (GitHub Actions), soʻrov chegaralari, xodim hisobini oʻchirish/qaytarish,
      frontend testlari.
- [x] Olib ketish kodi va u boʻyicha qidiruv.
- [x] Taom rasmlari va menyu kategoriyalari.
- [x] Profil, parolni oʻzgartirish va parolni tiklash oqimi.
- [x] Oshxona va tizim statistikasi.
- [x] **7-bosqich (1-qism)** — Telegram Mini App uchun `initData` autentifikatsiyasi.
- [x] **7-bosqich (2-qism)** — bot xabarnomalari: buyurtma holati oʻzgarganda mijozga xabar.
- [x] **8-bosqich (1-qism)** — backend koʻp tilliligi: uz, uz_Cyrl, ru, en.
- [x] **8-bosqich (2-qism)** — frontend toʻliq tarjimasi va til almashtirgich.
- [x] **8-bosqich (3-qism)** — menyu maʼlumotlari tarjimasi (nom, tavsif, kategoriya).
- [x] **9-bosqich** — modifikatorlar: backend, xodim paneli va mijoz tanlovi.
- [x] **7-bosqich (3-qism)** — interfeys Telegram qobigʻida: avtomatik kirish, mavzu,
      `MainButton` va `BackButton`.

Keyingi bosqichlar:

- [ ] Payme/Click integratsiyasi — merchant hisobi va kalitlari kerak, hozircha toʻlov
      simulyatsiya qilinadi.
- [ ] Real vaqtdagi bildirishnoma (WebSocket) — hozircha 15 soniyalik polling yetarli.
