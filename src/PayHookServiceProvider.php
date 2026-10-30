<?php

namespace PayHook\Laravel;

use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use PayHook\Laravel\Http\Middleware\VerifyPayHookRequest;
use PayHook\Laravel\Support\CacheReplayGuard;
use PayHook\Laravel\Support\ReplayGuard;

class PayHookServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/payhook.php', 'payhook');

        $this->app->singleton(PayHookManager::class, fn ($app) => new PayHookManager(
            $app->make(CacheFactory::class),
            (array) $app['config']->get('payhook', []),
        ));

        $this->app->bind(ReplayGuard::class, fn ($app) => new CacheReplayGuard(
            $app->make(PayHookManager::class)->cache()
        ));
    }

    public function boot(): void
    {
        $this->app->make(Router::class)->aliasMiddleware('payhook.verify', VerifyPayHookRequest::class);

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/payhook.php' => $this->app->configPath('payhook.php'),
            ], 'payhook-config');
        }

        if (config('payhook.routes.enabled', true)) {
            $this->loadRoutesFrom(__DIR__ . '/../routes/payhook.php');
        }
    }
}
