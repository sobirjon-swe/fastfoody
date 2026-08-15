# FastFoody — backend

Laravel 13 REST API. Autentifikatsiya — Laravel Sanctum (token), maʼlumotlar bazasi — MySQL.

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve   # http://localhost:8000
php artisan test
```

API endpointlari, rollar va demo hisoblar: [../README.md](../README.md)
