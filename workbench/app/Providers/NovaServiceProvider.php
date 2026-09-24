<?php

declare(strict_types=1);

namespace Workbench\App\Providers;

use BBSLab\LaravelForceTwoFactor\Facades\ForceTwoFactor;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Laravel\Fortify\Features;
use Laravel\Nova\Dashboard;
use Laravel\Nova\Dashboards\Main;
use Laravel\Nova\Nova;
use Laravel\Nova\NovaApplicationServiceProvider;
use Laravel\Nova\Tool;
use Workbench\App\Nova\User;

class NovaServiceProvider extends NovaApplicationServiceProvider
{
    public function boot(): void
    {
        parent::boot();

        // Enable Nova's built-in (Fortify) 2FA so the "User Security" page — the
        // enrolment target this package redirects to — actually renders.
        Nova::fortify()
            ->features([
                Features::twoFactorAuthentication(['confirm' => true, 'confirmPassword' => true]),
            ])
            ->register();

        // Demo of the bypass hook: exempt SSO-provisioned admins from the forced
        // 2FA enrolment. The callback receives the request and the un-enrolled
        // user; here it reads the seeded `is_sso` flag. A real app might instead
        // read a session attribute set at SSO login.
        ForceTwoFactor::bypass(
            fn (Request $request, Authenticatable $user): bool => $user->is_sso === true,
        );
    }

    protected function routes(): void
    {
        // Kept to the API shared by Nova 4 and 5 so the workbench boots on both
        // majors. withoutEmailVerificationRoutes() is Nova 5 only, so guard it.
        $registration = Nova::routes()
            ->withAuthenticationRoutes(default: true)
            ->withPasswordResetRoutes();

        if (method_exists($registration, 'withoutEmailVerificationRoutes')) {
            $registration->withoutEmailVerificationRoutes();
        }

        $registration->register();
    }

    protected function gate(): void
    {
        Gate::define('viewNova', fn ($user) => true);
    }

    /**
     * @return array<int, Dashboard>
     */
    protected function dashboards(): array
    {
        return [
            new Main,
        ];
    }

    /**
     * @return array<int, Tool>
     */
    public function tools(): array
    {
        return [];
    }

    protected function resources(): void
    {
        Nova::resources([
            User::class,
        ]);
    }
}
