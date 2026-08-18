<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Responses shape their own keys ("user", "token", ...), so resources
        // are not wrapped in an extra "data" envelope.
        JsonResource::withoutWrapping();

        // Fail loudly in development instead of silently returning null for a
        // relation that was never eager loaded. Testlarda ham yoqilgan:
        // aks holda kechikkan yuklash xatosi faqat brauzerda bilinardi.
        Model::preventLazyLoading($this->app->isLocal() || $this->app->runningUnitTests());

        // Tiklash havolasi API'ga emas, SPA sahifasiga olib boradi.
        ResetPassword::createUrlUsing(fn (object $user, string $token) => sprintf(
            '%s/parolni-tiklash?token=%s&email=%s',
            rtrim((string) config('fastfoody.frontend_url'), '/'),
            $token,
            urlencode($user->getEmailForPasswordReset()),
        ));
    }
}
