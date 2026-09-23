<?php

declare(strict_types=1);

namespace BBSLab\NovaForceTwoFactor;

use BBSLab\NovaForceTwoFactor\Facades\ForceTwoFactor;
use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;

/**
 * Runtime configuration point for the package, resolved as a container singleton
 * and reachable through the {@see ForceTwoFactor}
 * facade. Callbacks live here (never in the config file) so they survive
 * `config:cache`, which cannot serialise a Closure.
 */
class TwoFactorManager
{
    /**
     * Callbacks that exempt an admin from the forced 2FA enrolment. The request
     * is bypassed as soon as any of them returns true, so several independent
     * reasons to skip enrolment compose without clobbering.
     *
     * @var array<int, Closure(Request, Authenticatable): bool>
     */
    protected array $bypassCallbacks = [];

    /**
     * Register a callback that exempts a request from the forced 2FA enrolment —
     * for instance SSO admins whose second factor is handled by the identity
     * provider. The callback receives the current request and the authenticated,
     * not-yet-enrolled user, and returns true to skip the enrolment redirect.
     *
     * @param  Closure(Request, Authenticatable): bool  $callback
     */
    public function bypass(Closure $callback): void
    {
        $this->bypassCallbacks[] = $callback;
    }

    /**
     * Whether any registered callback exempts this request/user from enrolment.
     */
    public function shouldBypass(Request $request, Authenticatable $user): bool
    {
        foreach ($this->bypassCallbacks as $callback) {
            if ($callback($request, $user)) {
                return true;
            }
        }

        return false;
    }
}
