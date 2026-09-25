<?php

declare(strict_types=1);

use BBSLab\LaravelForceTwoFactor\Facades\ForceTwoFactor;
use BBSLab\NovaForceTwoFactor\Http\Middleware\EnsureTwoFactorEnabled;
use BBSLab\NovaToast\Toast;
use Illuminate\Auth\GenericUser;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Workbench\App\Models\User;

use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\postJson;

beforeEach(function (): void {
    config(['laravel-force-two-factor.enabled' => true]);

    // CSRF is irrelevant here and its class was renamed across Laravel majors,
    // so disable both possible names (unknown names are ignored).
    $this->withoutMiddleware([
        VerifyCsrfToken::class,
        ValidateCsrfToken::class,
    ]);

    Route::middleware(['web', EnsureTwoFactorEnabled::class])->group(function (): void {
        Route::get('/panel', fn (): string => 'panel')->name('panel');
        Route::post('/panel', fn (): string => 'saved')->name('panel.store');
        Route::get('/nova/user-security', fn (): string => 'security')->name('nova.pages.user-security');
        Route::get('/nova/user-security/confirm', fn (): string => 'confirm');
        Route::get('/nova-script', fn (): string => 'asset')->name('nova.asset.script');
        Route::post('/logout', fn (): string => 'bye')->name('nova.logout');
        Route::get('/rotate', fn (): string => 'rotate')->name('nova-password-rotation.expired.show');
        Route::get('/reports', fn (): string => 'reports')->name('reports.index');
        Route::get('/public/info', fn (): string => 'info');
    });
});

/**
 * Authenticate as an admin who has (or has not) enrolled in 2FA. The user is
 * not persisted — the middleware only reads it off the guard.
 */
function loginAdmin(bool $enrolled = false): User
{
    $factory = User::factory();

    if ($enrolled) {
        $factory = $factory->withTwoFactor();
    }

    $user = $factory->make();
    test()->actingAs($user);

    return $user;
}

it('passes through when enforcement is disabled', function (): void {
    config(['laravel-force-two-factor.enabled' => false]);
    loginAdmin(enrolled: false);

    get('/panel')->assertOk()->assertSee('panel');
});

it('passes through for an unauthenticated request', function (): void {
    get('/panel')->assertOk()->assertSee('panel');
});

it('passes through for an admin who has enabled two-factor', function (): void {
    loginAdmin(enrolled: true);

    get('/panel')->assertOk()->assertSee('panel');
});

it('passes through a user model without Nova two-factor support', function (): void {
    // A user that does not carry Fortify's hasEnabledTwoFactorAuthentication().
    test()->actingAs(new GenericUser(['id' => 1, 'name' => 'Legacy']));

    get('/panel')->assertOk()->assertSee('panel');
});

it('redirects an un-enrolled page visit to user security with a toast', function (): void {
    loginAdmin();

    get('/panel', ['Accept' => 'text/html'])
        ->assertRedirect(route('nova.pages.user-security'))
        ->assertSessionHas(Toast::SESSION_KEY);
});

it('sends an Inertia visit a client-side redirect with a toast', function (): void {
    loginAdmin();

    $response = get('/panel', ['X-Inertia' => 'true']);

    $response->assertNoContent(409)
        ->assertHeader('X-Inertia-Location', route('nova.pages.user-security'))
        ->assertSessionHas(Toast::SESSION_KEY);
});

it('lets a background XHR read through', function (): void {
    loginAdmin();

    get('/panel', ['X-Requested-With' => 'XMLHttpRequest'])->assertOk();
});

it('blocks a background XHR write with a 403', function (): void {
    loginAdmin();

    postJson('/panel')->assertForbidden();
});

it('blocks a non-GET navigation (form POST) with a 403', function (): void {
    loginAdmin();

    // A write is never a navigation, even when it accepts HTML — it must not be
    // redirected (that would drop the request), only blocked.
    post('/panel', [], ['Accept' => 'text/html'])->assertForbidden();
});

it('always allows Nova asset requests', function (): void {
    loginAdmin();

    get('/nova-script')->assertOk()->assertSee('asset');
});

it('always allows the user-security enrolment subtree', function (): void {
    loginAdmin();

    get('/nova/user-security')->assertOk()->assertSee('security');
    get('/nova/user-security/confirm')->assertOk()->assertSee('confirm');
});

it('allow-lists the user-security subtree when the Nova path is root ("/")', function (): void {
    // A root-mounted Nova (config nova.path = "/") makes Nova::path() return "/";
    // the enrolment subtree must still be reachable so an un-enrolled admin can set 2FA up.
    config(['nova.path' => '/']);

    Route::middleware(['web', EnsureTwoFactorEnabled::class])->group(function (): void {
        Route::get('/user-security', fn (): string => 'security')->name('nova.pages.user-security');
        Route::get('/user-security/two-factor-authentication', fn (): string => 'enable');
    });

    loginAdmin(); // un-enrolled

    get('/user-security', ['Accept' => 'text/html'])->assertOk()->assertSee('security');
    get('/user-security/two-factor-authentication', ['Accept' => 'text/html'])->assertOk()->assertSee('enable');
});

it('still redirects an un-enrolled admin to user security when the Nova path is root ("/")', function (): void {
    config(['nova.path' => '/']);

    Route::middleware(['web', EnsureTwoFactorEnabled::class])->group(function (): void {
        Route::get('/dashboard', fn (): string => 'dash');
        Route::get('/user-security', fn (): string => 'security')->name('nova.pages.user-security');
    });

    loginAdmin();

    get('/dashboard', ['Accept' => 'text/html'])->assertRedirect(route('nova.pages.user-security'));
});

it('never blocks the Fortify 2FA enrolment endpoints under user-security', function (string $path): void {
    Route::middleware(['web', EnsureTwoFactorEnabled::class])->get($path, fn (): string => 'ok');
    loginAdmin(); // un-enrolled — must still reach every enrolment endpoint

    get('/'.$path, ['Accept' => 'text/html'])->assertOk()->assertSee('ok');
})->with([
    'confirm password' => ['nova/user-security/confirm-password'],
    'enable' => ['nova/user-security/two-factor-authentication'],
    'secret key' => ['nova/user-security/two-factor-secret-key'],
    'qr code' => ['nova/user-security/two-factor-qr-code'],
    'confirm' => ['nova/user-security/confirmed-two-factor-authentication'],
    'recovery codes' => ['nova/user-security/two-factor-recovery-codes'],
]);

it('always allows logout (POST), even with every configured exception cleared', function (): void {
    // logout is anti-lockout and hardcoded, so clearing except.* must not block it.
    config(['nova-force-two-factor.except.routes' => [], 'nova-force-two-factor.except.paths' => []]);
    loginAdmin();

    post('/logout')->assertOk()->assertSee('bye');
});

it('allows the default excepted route (expired password)', function (): void {
    loginAdmin();

    get('/rotate')->assertOk()->assertSee('rotate');
});

it('allows a consumer-configured excepted route', function (): void {
    config(['nova-force-two-factor.except.routes' => ['reports.*']]);
    loginAdmin();

    get('/reports')->assertOk()->assertSee('reports');
});

it('allows a consumer-configured excepted path', function (): void {
    config(['nova-force-two-factor.except.paths' => ['public/*']]);
    loginAdmin();

    get('/public/info')->assertOk()->assertSee('info');
});

it('still redirects when no exceptions are configured', function (): void {
    config([
        'nova-force-two-factor.except.routes' => [],
        'nova-force-two-factor.except.paths' => [],
    ]);
    loginAdmin();

    get('/reports', ['Accept' => 'text/html'])
        ->assertRedirect(route('nova.pages.user-security'));
});

it('lets an un-enrolled admin through when a bypass callback returns true', function (): void {
    ForceTwoFactor::bypass(fn (): bool => true);
    loginAdmin();

    get('/panel', ['Accept' => 'text/html'])->assertOk()->assertSee('panel');
});

it('still redirects an un-enrolled admin when the bypass callback returns false', function (): void {
    ForceTwoFactor::bypass(fn (): bool => false);
    loginAdmin();

    get('/panel', ['Accept' => 'text/html'])
        ->assertRedirect(route('nova.pages.user-security'));
});

it('hands the request and the un-enrolled user to the bypass callback', function (): void {
    $received = null;
    ForceTwoFactor::bypass(function (Request $request, Authenticatable $user) use (&$received): bool {
        $received = [$request, $user];

        return true;
    });
    loginAdmin();

    get('/panel', ['Accept' => 'text/html'])->assertOk();

    expect($received[0])->toBeInstanceOf(Request::class)
        ->and($received[1])->toBeInstanceOf(Authenticatable::class);
});
