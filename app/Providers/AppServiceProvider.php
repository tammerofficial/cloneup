<?php

namespace App\Providers;

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
        // Safeguard: disable broadcasting at runtime on shared hosting if misconfigured
        $default = config('broadcasting.default');
        if ($default !== 'null') {
            $reverb = config('broadcasting.connections.reverb');
            $host = data_get($reverb, 'options.host');
            $key = data_get($reverb, 'key');
            $secret = data_get($reverb, 'secret');
            if (empty($key) || empty($secret) || empty($host) || str_contains((string) $host, 'localhost')) {
                config(['broadcasting.default' => 'null']);
            }
        }
    }
}
