<?php

namespace App\Providers;

use App\Services\Gemini\GeminiClient;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(GeminiClient::class, fn () => new GeminiClient((string) config('lectta.gemini_api_key')));
    }

    public function boot(): void
    {
        if ($this->app->isProduction()) {
            URL::forceScheme('https');
        }
    }
}
