<?php

namespace Franciscodlxdev\SocialLogin;

use Franciscodlxdev\SocialLogin\Commands\AndroidRedirectSchemes;
use Franciscodlxdev\SocialLogin\Native\AuthSessionBridge;
use Franciscodlxdev\SocialLogin\Native\NativePhpBridge;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Support\ServiceProvider;

class SocialLoginServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/social-login.php', 'social-login');

        $this->app->singleton(AuthSessionBridge::class, NativePhpBridge::class);
        $this->app->singleton(PendingSignIns::class, fn ($app) => new PendingSignIns(
            $app->make(CacheFactory::class)->store(),
            (int) $app['config']->get('social-login.pending_ttl', 600),
        ));
        $this->app->singleton(SocialLogin::class);
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/social-login.php' => config_path('social-login.php'),
        ], 'social-login-config');

        if ($this->app->runningInConsole()) {
            $this->commands([AndroidRedirectSchemes::class]);
        }
    }
}
