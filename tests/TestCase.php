<?php

declare(strict_types=1);

namespace BBSLab\NovaForceTwoFactor\Tests;

use BBSLab\LaravelForceTwoFactor\LaravelForceTwoFactorServiceProvider;
use BBSLab\NovaForceTwoFactor\NovaForceTwoFactorServiceProvider;
use BBSLab\NovaToast\NovaToastServiceProvider;
use Illuminate\Foundation\Application;
use Laravel\Nova\NovaCoreServiceProvider;
use Orchestra\Testbench\Concerns\WithWorkbench;
use Orchestra\Testbench\TestCase as Orchestra;

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
            // The framework-agnostic base (auto-discovered in a real app): binds
            // the shared TwoFactorManager registry the middleware reads from.
            LaravelForceTwoFactorServiceProvider::class,
            NovaForceTwoFactorServiceProvider::class,
        ];
    }
}
