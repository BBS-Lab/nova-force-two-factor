<?php

declare(strict_types=1);

use BBSLab\NovaForceTwoFactor\Http\Middleware\EnsureTwoFactorEnabled;
use BBSLab\NovaForceTwoFactor\NovaForceTwoFactorServiceProvider;

function forceTwoFactorProvider(): NovaForceTwoFactorServiceProvider
{
    return new NovaForceTwoFactorServiceProvider(app());
}

/**
 * @return int occurrences of the middleware in the `nova` router group
 */
function novaGroupCount(): int
{
    $group = app('router')->getMiddlewareGroups()['nova'] ?? [];

    return count(array_keys($group, EnsureTwoFactorEnabled::class, true));
}

it('appends the middleware to the nova.middleware array', function (): void {
    config([
        'nova.middleware' => ['Existing\\Middleware'],
        'nova-force-two-factor.auto_register_middleware' => true,
    ]);

    forceTwoFactorProvider()->packageRegistered();

    expect(config('nova.middleware'))->toContain(EnsureTwoFactorEnabled::class);
});

it('does not append the middleware twice', function (): void {
    config([
        'nova.middleware' => ['Existing\\Middleware'],
        'nova-force-two-factor.auto_register_middleware' => true,
    ]);

    forceTwoFactorProvider()->packageRegistered();
    forceTwoFactorProvider()->packageRegistered();

    $count = count(array_keys(config('nova.middleware'), EnsureTwoFactorEnabled::class, true));

    expect($count)->toBe(1);
});

it('does not touch nova.middleware when auto registration is off', function (): void {
    config([
        'nova.middleware' => ['Existing\\Middleware'],
        'nova-force-two-factor.auto_register_middleware' => false,
    ]);

    forceTwoFactorProvider()->packageRegistered();

    expect(config('nova.middleware'))->toBe(['Existing\\Middleware']);
});

it('leaves an unpopulated nova.middleware untouched', function (mixed $value): void {
    config([
        'nova.middleware' => $value,
        'nova-force-two-factor.auto_register_middleware' => true,
    ]);

    forceTwoFactorProvider()->packageRegistered();

    expect(config('nova.middleware'))->toBe($value);
})->with([
    'null' => [null],
    'empty array' => [[]],
]);

it('auto-registers the middleware in the nova group on boot', function (): void {
    // No manual call — the provider's packageBooted() / app->booted() chain ran
    // during test setup, with auto-registration on by default.
    expect(novaGroupCount())->toBeGreaterThanOrEqual(1);
});

it('pushes the middleware into the nova router group', function (): void {
    config(['nova-force-two-factor.auto_register_middleware' => true]);

    forceTwoFactorProvider()->registerGroupMiddleware();

    expect(app('router')->getMiddlewareGroups()['nova'] ?? [])
        ->toContain(EnsureTwoFactorEnabled::class);
});

it('does not push into the nova router group when auto registration is off', function (): void {
    config(['nova-force-two-factor.auto_register_middleware' => false]);

    $before = novaGroupCount();
    forceTwoFactorProvider()->registerGroupMiddleware();

    expect(novaGroupCount())->toBe($before);
});
