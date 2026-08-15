<?php

namespace App\Providers;

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
        // relation that was never eager loaded.
        Model::preventLazyLoading($this->app->isLocal());
    }
}
