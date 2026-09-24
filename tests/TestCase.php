<?php

declare(strict_types=1);

namespace BBSLab\NovaForceTwoFactor\Tests;

use BBSLab\LaravelForceTwoFactor\LaravelForceTwoFactorServiceProvider;
use BBSLab\LaravelOkta\LaravelOktaServiceProvider;
use BBSLab\LaravelPasswordRotation\LaravelPasswordRotationServiceProvider;
use BBSLab\NovaForceTwoFactor\NovaForceTwoFactorServiceProvider;
use BBSLab\NovaToast\NovaToastServiceProvider;
use Illuminate\Foundation\Application;
use Laravel\Nova\NovaCoreServiceProvider;
use Orchestra\Testbench\Concerns\WithWorkbench;
use Orchestra\Testbench\TestCase as Orchestra;
use SocialiteProviders\Manager\ServiceProvider as SocialiteManagerServiceProvider;

abstract class TestCase extends Orchestra
{
    use WithWorkbench;

    /**
     * @param  Application  $app
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            NovaCoreServiceProvider::class,
            NovaToastServiceProvider::class,
            SocialiteManagerServiceProvider::class,
            // The framework-agnostic base binds the shared TwoFactorManager registry
            // the middleware reads from. The real sibling packages are booted too so
            // the end-to-end interop test exercises their ACTUAL registrations (not a
            // hand-made closure): laravel-password-rotation registers the
            // owes-rotation → 2FA bypass; laravel-okta registers okta_authenticated
            // → both the 2FA and the password-rotation registries.
            LaravelForceTwoFactorServiceProvider::class,
            LaravelPasswordRotationServiceProvider::class,
            LaravelOktaServiceProvider::class,
            NovaForceTwoFactorServiceProvider::class,
        ];
    }
}
