<?php

namespace App\Providers;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ImageManager::class, function ($app) {
            return new ImageManager(new Driver());
        });
    }

    public function boot(): void
    {
        $appUrl = rtrim(config('app.url'), '/');
        $parsed = $appUrl ? parse_url($appUrl) : null;

        if (is_array($parsed) && isset($parsed['scheme'], $parsed['host'])) {
            $forcedRoot = $parsed['scheme'].'://'.$parsed['host'];
            if (isset($parsed['port'])) {
                $forcedRoot .= ':' . $parsed['port'];
            }
            URL::forceRootUrl($forcedRoot);
        } elseif (! empty($appUrl)) {
            URL::forceRootUrl($appUrl);
        }

        $basePath = admin_base_prefix();
        $appBaseUrl = rtrim(admin_url(), '/');

        View::share('appBasePath', $basePath);
        View::share('appBaseUrl', $appBaseUrl);
    }
}
