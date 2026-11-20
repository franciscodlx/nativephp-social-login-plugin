<?php

namespace Franciscodlxdev\SocialLogin\Tests;

use Franciscodlxdev\SocialLogin\Native\AuthSessionBridge;
use Franciscodlxdev\SocialLogin\SocialLoginServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected FakeBridge $bridge;

    protected function getPackageProviders($app): array
    {
        return [SocialLoginServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('cache.default', 'array');
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->bridge = new FakeBridge;
        $this->app->instance(AuthSessionBridge::class, $this->bridge);
    }
}
