<?php

declare(strict_types=1);

namespace BBSLab\NovaForceTwoFactor\Facades;

use BBSLab\NovaForceTwoFactor\TwoFactorManager;
use Illuminate\Support\Facades\Facade;

/**
 * @method static void bypass(\Closure $callback)
 * @method static bool shouldBypass(\Illuminate\Http\Request $request, \Illuminate\Contracts\Auth\Authenticatable $user)
 *
 * @see TwoFactorManager
 */
class ForceTwoFactor extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return TwoFactorManager::class;
    }
}
