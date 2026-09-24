<?php

declare(strict_types=1);

use BBSLab\LaravelOkta\Http\Controllers\OktaController;
use BBSLab\NovaForceTwoFactor\Http\Middleware\EnsureTwoFactorEnabled;
use BBSLab\NovaPasswordRotation\Http\Middleware\EnsurePasswordIsNotExpired;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Route;
use Workbench\App\Models\User;

use function Pest\Laravel\get;

beforeEach(function (): void {
    config([
        'laravel-force-two-factor.enabled' => true,
        'laravel-password-rotation.enabled' => true,
        'laravel-password-rotation.days' => 90,
        'laravel-password-rotation.force_on_first_login' => false,
    ]);

    $this->withoutMiddleware([VerifyCsrfToken::class, ValidateCsrfToken::class]);

    // Both real middlewares, on every route, in the order Nova's group would run
    // them (force-2FA first). The redirect targets are lightweight stand-ins for
    // Nova's real pages so we exercise the middleware interaction, not rendering.
    Route::middleware(['web', EnsureTwoFactorEnabled::class, EnsurePasswordIsNotExpired::class])
        ->group(function (): void {
            Route::get('/panel', fn (): string => 'panel')->name('panel');
            Route::get('/nova/user-security', fn (): string => 'user-security')->name('nova.pages.user-security');
            Route::get('/nova/password-rotation/expired', fn (): string => 'change-your-password')
                ->name('nova-password-rotation.expired.show');
        });
});

/**
 * Authenticate as an admin with the given 2FA enrolment and password state.
 * Not persisted — both middlewares read the user off the guard.
 */
function loginWith(bool $twoFactor, bool $expired): User
{
    $factory = User::factory();

    if ($twoFactor) {
        $factory = $factory->withTwoFactor();
    }

    if ($expired) {
        $factory = $factory->passwordExpired();
    }

    $user = $factory->make();
    test()->actingAs($user);

    return $user;
}

it('lets an enrolled admin with a fresh password reach Nova', function (): void {
    loginWith(twoFactor: true, expired: false);

    get('/panel', ['Accept' => 'text/html'])->assertOk()->assertSee('panel');
});

it('forces an un-enrolled admin with a fresh password to 2FA enrolment', function (): void {
    loginWith(twoFactor: false, expired: false);

    get('/panel', ['Accept' => 'text/html'])
        ->assertRedirect(route('nova.pages.user-security'));
});

it('forces an enrolled admin with an expired password to change it', function (): void {
    loginWith(twoFactor: true, expired: true);

    get('/panel', ['Accept' => 'text/html'])
        ->assertRedirect(route('nova-password-rotation.expired.show'));
});

it('funnels an un-enrolled admin with an expired password to the change screen first', function (): void {
    loginWith(twoFactor: false, expired: true);

    // Both middlewares want to act; following the chain must terminate on the
    // password-change screen (password rotation wins) — and never loop.
    test()->followingRedirects()
        ->get('/panel', ['Accept' => 'text/html'])
        ->assertOk()
        ->assertSee('change-your-password');
});

it('lets an un-enrolled admin with an expired password reach the password-rotation page', function (): void {
    // This is why nova-force-two-factor excepts nova-password-rotation.expired.*:
    // an un-enrolled admin must still be able to open the change screen.
    loginWith(twoFactor: false, expired: true);

    get('/nova/password-rotation/expired', ['Accept' => 'text/html'])
        ->assertOk()
        ->assertSee('change-your-password');
});

// --- End-to-end interop with the REAL sibling packages (registry-driven) -------

it('gives rotation precedence at the 2FA gate via the shared registry, without the except.routes crutch', function (): void {
    // Drop the static except.routes fallback: only the owes-rotation bypass that
    // bbs-lab/laravel-password-rotation registers into the ForceTwoFactor registry
    // can make the 2FA gate yield. An un-enrolled + expired admin must then be sent
    // STRAIGHT to the rotation screen — never bounced through the 2FA set-up page.
    config(['nova-force-two-factor.except.routes' => []]);
    loginWith(twoFactor: false, expired: true);

    get('/panel', ['Accept' => 'text/html'])
        ->assertRedirect(route('nova-password-rotation.expired.show'));
});

it('lets an Okta-authenticated admin bypass BOTH the 2FA gate and the rotation gate', function (): void {
    // The real laravel-okta registration exempts okta_authenticated sessions from
    // both registries, so an Okta admin who is un-enrolled AND has an expired
    // password still reaches the panel — proving "2FA except Okta" and
    // "rotation except Okta" against the actual packages, not a test closure.
    config(['nova-force-two-factor.except.routes' => []]);
    $user = User::factory()->passwordExpired()->make();

    test()->actingAs($user)
        ->withSession([OktaController::AUTHENTICATED_SESSION_KEY => true])
        ->get('/panel', ['Accept' => 'text/html'])
        ->assertOk()
        ->assertSee('panel');
});
