<?php

declare(strict_types=1);

namespace BBSLab\NovaForceTwoFactor;

use BBSLab\NovaForceTwoFactor\Http\Middleware\EnsureTwoFactorEnabled;
use Illuminate\Support\Facades\Route;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class NovaForceTwoFactorServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('nova-force-two-factor')
            ->hasConfigFile()
            ->hasTranslations();
    }

    /**
     * Nova 4 bakes the `nova.middleware` config array inline into its routes,
     * so append our middleware there while that array still exists.
     */
    public function packageRegistered(): void
    {
        if (! $this->autoRegistersMiddleware()) {
            return;
        }

        $middleware = config('nova.middleware');

        // Only extend an already-populated array — never create or clobber it.
        if (! is_array($middleware) || $middleware === []) {
            return;
        }

        if (! in_array(EnsureTwoFactorEnabled::class, $middleware, true)) {
            $middleware[] = EnsureTwoFactorEnabled::class;
            config(['nova.middleware' => $middleware]);
        }
    }

    /**
     * Nova 5 builds a `nova` router middleware group; push into it once every
     * provider (Nova included) has booted. Covers setups where the
     * `nova.middleware` config was never published.
     */
    public function packageBooted(): void
    {
        $this->app->booted(function (): void {
            $this->registerGroupMiddleware();
        });
    }

    public function registerGroupMiddleware(): void
    {
        if (! $this->autoRegistersMiddleware()) {
            return;
        }

        // pushMiddlewareToGroup() de-duplicates, so this is safe alongside the
        // packageRegistered() append on stacks that read both.
        Route::pushMiddlewareToGroup('nova', EnsureTwoFactorEnabled::class);
    }

    protected function autoRegistersMiddleware(): bool
    {
        return (bool) config('nova-force-two-factor.auto_register_middleware');
    }
}
