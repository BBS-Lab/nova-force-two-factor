# Nova Force Two Factor

[![Latest Version on Packagist](https://img.shields.io/packagist/v/bbs-lab/nova-force-two-factor.svg?style=flat-square)](https://packagist.org/packages/bbs-lab/nova-force-two-factor)
[![Tests](https://img.shields.io/github/actions/workflow/status/BBS-Lab/nova-force-two-factor/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/BBS-Lab/nova-force-two-factor/actions)
[![Total Downloads](https://img.shields.io/packagist/dt/bbs-lab/nova-force-two-factor.svg?style=flat-square)](https://packagist.org/packages/bbs-lab/nova-force-two-factor)

Make [Laravel Nova](https://nova.laravel.com)'s built-in two-factor authentication **mandatory**. Nova's
2FA (via [Fortify](https://laravel.com/docs/fortify)) is opt-in; this package adds a middleware that
redirects any authenticated admin who has **not** enrolled to the **User Security** page — with a toast
explaining why — until they set it up.

The redirect notice is shown with [`bbs-lab/nova-toast`](https://github.com/BBS-Lab/nova-toast).

## Requirements

- PHP `^8.2`
- Laravel Nova `^5.0`
- Laravel `^11.0 || ^12.0 || ^13.0`

Nova's built-in two-factor authentication must be enabled (`Nova::fortify()` with
`Features::twoFactorAuthentication()`), and your Nova user must carry Fortify's
`Laravel\Fortify\TwoFactorAuthenticatable` trait — the middleware reads its
`hasEnabledTwoFactorAuthentication()` method. A user model without that method is left alone.

> Nova's built-in User Security / 2FA page (`nova.pages.user-security`) is **Nova 5 only**, so this package
> requires Nova 5. It is exercised in CI on Laravel 11/12/13 and PHP 8.3/8.4/8.5.

## Installation

Because Nova is a paid, private package, make sure your application is already authenticated against
`nova.laravel.com`, then:

```bash
composer require bbs-lab/nova-force-two-factor
```

The service provider is auto-discovered and injects the middleware into Nova's stack for you — there is
nothing else to wire up. It is enforced by default.

## Configuration

Publish the config if you want to tweak it:

```bash
php artisan vendor:publish --tag="nova-force-two-factor-config"
```

```php
return [

    // Enforce 2FA enrolment. Set NOVA_FORCE_TWO_FACTOR=false to disable (e.g. locally).
    'enabled' => (bool) env('NOVA_FORCE_TWO_FACTOR', true),

    // Let the package inject the middleware into Nova's stack. Turn off to wire it up yourself.
    'auto_register_middleware' => (bool) env('NOVA_FORCE_TWO_FACTOR_AUTO_MIDDLEWARE', true),

    // Requests matching any of these are let through even when the admin has not enrolled.
    'except' => [
        'routes' => [
            'nova-password-rotation.expired.*',
        ],
        'paths' => [
            //
        ],
    ],

];
```

- **`except.routes`** is matched with `$request->routeIs(...)` and **`except.paths`** with `$request->is(...)`.
  Both accept the wildcards Laravel understands, so exact route names, route-name patterns, exact URLs and
  URL patterns are all covered.
- Nova's **assets**, **logout** and the **User Security enrolment subtree** are always allowed and cannot be
  blocked — they don't need listing (and can't be removed), so an admin can never lock themselves out or
  break the SPA.

### Bypassing enrolment per request (e.g. SSO admins)

`except.*` matches URLs. To exempt specific **users** instead — for instance SSO admins whose second
factor is handled by the identity provider and who shouldn't be pushed into local 2FA — register a bypass
callback via the `ForceTwoFactor` facade in a service provider's `boot()`. It receives the request and the
un-enrolled admin; returning `true` lets the request through:

```php
use BBSLab\NovaForceTwoFactor\Facades\ForceTwoFactor;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;

ForceTwoFactor::bypass(
    fn (Request $request, Authenticatable $user) => $request->hasSession() && $request->session()->get('sso') === true,
);
```

Register the callback in code, **not** in config (a closure breaks `config:cache`), and guard
`hasSession()` before reading the session. Several callbacks may be registered — the request is exempt as
soon as any returns `true`.

The toast wording lives in the package translations; publish them to customise:

```bash
php artisan vendor:publish --tag="nova-force-two-factor-translations"
```

## How it works

The middleware runs through Nova's `nova` middleware group, which is nested inside the api/asset stacks
too — so it sees page visits **and** every script/style/XHR request. It treats each kind correctly:

- **Full-page navigation** (a GET that accepts HTML) → `302` redirect to User Security, with a toast.
- **Inertia visit** (`X-Inertia`) → `409` + `X-Inertia-Location`, with a toast (Nova redirects client-side).
- **Background XHR** → never redirected: reads pass through, writes are blocked with `403`. Redirecting these
  would break the SPA and enrolment itself.

An admin who has enrolled, an anonymous request, the allow-listed routes/paths, and any request a
registered bypass callback exempts all pass straight through.

### Manual middleware registration

Set `auto_register_middleware` to `false` and add the middleware where you want it, e.g. in `config/nova.php`:

```php
use BBSLab\NovaForceTwoFactor\Http\Middleware\EnsureTwoFactorEnabled;

'middleware' => [
    // …
    EnsureTwoFactorEnabled::class,
],
```

## Testing

```bash
composer test            # Pest suite
composer test-coverage   # 100% line coverage on src/
composer test-mutation   # mutation testing (MSI ≥ 80%)
composer analyse         # PHPStan level 8
composer format          # Pint (laravel preset + strict types)
```

A full embedded Nova app (via [Orchestra Workbench](https://github.com/orchestral/workbench)) lets you
exercise the flow in a real Nova instance:

```bash
composer serve   # boots Nova at http://localhost:8000/nova
```

## Security

The middleware only gates access and flashes a message — it stores no data and exposes no endpoint. If you
discover a security issue, please email `paris@big-boss-studio.com` instead of using the issue tracker.

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Credits

- [Big Boss Studio](https://github.com/BBS-Lab)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
