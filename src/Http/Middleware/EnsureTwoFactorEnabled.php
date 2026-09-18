<?php

declare(strict_types=1);

namespace BBSLab\NovaForceTwoFactor\Http\Middleware;

use BBSLab\NovaToast\Toast;
use Closure;
use Illuminate\Http\Request;
use Laravel\Nova\Nova;
use Symfony\Component\HttpFoundation\Response;

/**
 * Force every Nova admin to enrol in two-factor authentication.
 *
 * Nova's built-in 2FA (Fortify) is opt-in; this middleware makes it mandatory:
 * an authenticated admin who has not fully enabled 2FA is redirected to the
 * "User Security" page — with a {@see Toast} explaining why — until they enrol.
 *
 * The middleware runs through Nova's "nova" group, which is also nested inside
 * the api/asset stacks, so it sees page visits AND every script/style/XHR
 * request. Only genuine navigations are redirected; Nova's assets, logout and
 * the whole User Security enrolment subtree (its 2FA setup endpoints are unnamed
 * Fortify routes) are always allow-listed, and background XHR are read-through /
 * write-blocked — otherwise the SPA, and enrolment itself, would break.
 */
class EnsureTwoFactorEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('nova-force-two-factor.enabled')) {
            return $next($request);
        }

        if ($this->shouldPassThrough($request)) {
            return $next($request);
        }

        $location = route('nova.pages.user-security');

        // Inertia visit → client-side redirect (409 + X-Inertia-Location header).
        if ($request->header('X-Inertia')) {
            Toast::warning($this->notice());

            return response('', Response::HTTP_CONFLICT, ['X-Inertia-Location' => $location]);
        }

        // Full-page document navigation → standard 302 redirect.
        if ($request->isMethod('GET') && $request->acceptsHtml() && ! $request->ajax()) {
            Toast::warning($this->notice());

            return redirect($location);
        }

        // Background XHR: never redirect (Nova would follow it and reload the
        // setup page, breaking enrolment). Allow reads, block writes.
        return $request->isMethod('GET')
            ? $next($request)
            : abort(Response::HTTP_FORBIDDEN, (string) trans('nova-force-two-factor::messages.required_error'));
    }

    /**
     * An authenticated, 2FA-enabled admin, an anonymous request, the always-on
     * anti-lockout allow-list (Nova assets, logout + the User Security enrolment
     * subtree) and the configured exceptions all pass straight through.
     */
    protected function shouldPassThrough(Request $request): bool
    {
        $user = Nova::user($request);

        // Anonymous, or a user model without Nova's built-in (Fortify) 2FA —
        // there is nothing to enforce, so let the request through. The host's
        // Nova user gets hasEnabledTwoFactorAuthentication() from Fortify's
        // TwoFactorAuthenticatable trait (Nova's built-in 2FA).
        if ($user === null || ! method_exists($user, 'hasEnabledTwoFactorAuthentication')) {
            return true;
        }

        if ($user->hasEnabledTwoFactorAuthentication()) {
            return true;
        }

        $novaPath = trim(Nova::path(), '/');

        // Anti-lockout: never block Nova's own assets, logout, or the enrolment
        // page, or an admin could brick themselves / break the SPA. Not
        // configurable — a consumer editing except.* can never strip these.
        if ($request->routeIs('nova.asset.*', 'nova.logout')
            || $request->is("{$novaPath}/user-security", "{$novaPath}/user-security/*")
        ) {
            return true;
        }

        return $this->isExcepted($request);
    }

    /**
     * Consumer-defined allow-list. Route names and URL paths both accept the
     * wildcards Laravel's routeIs()/is() understand, so exact names, route
     * patterns, exact URLs and URL patterns are all covered by two keys.
     */
    protected function isExcepted(Request $request): bool
    {
        $routes = (array) config('nova-force-two-factor.except.routes', []);
        $paths = (array) config('nova-force-two-factor.except.paths', []);

        // routeIs()/is() return false for an empty pattern list, so no guard.
        return $request->routeIs(...$routes)
            || $request->is(...$paths);
    }

    protected function notice(): string
    {
        return (string) trans('nova-force-two-factor::messages.required_notice');
    }
}
