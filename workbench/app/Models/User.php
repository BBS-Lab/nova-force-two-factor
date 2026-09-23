<?php

declare(strict_types=1);

namespace Workbench\App\Models;

use BBSLab\LaravelPasswordRotation\Concerns\RotatesPassword;
use BBSLab\LaravelPasswordRotation\Contracts\MustRotatePassword;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Workbench\Database\Factories\UserFactory;

/**
 * Carries both concerns so the workbench can demonstrate every combination of
 * 2FA enrolment and password-rotation state. Fortify's TwoFactorAuthenticatable
 * lets Nova's User Security page render; the password-rotation contract is
 * implemented by the (dev-only) bbs-lab/nova-password-rotation package.
 */
class User extends Authenticatable implements MustRotatePassword
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, RotatesPassword, TwoFactorAuthenticatable;

    /**
     * Workbench models live outside the app namespace Laravel guesses factories
     * from, so point at the workbench factory explicitly.
     */
    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'two_factor_enabled',
        'password_changed_at',
        'is_sso',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_enabled' => 'boolean',
            'two_factor_confirmed_at' => 'datetime',
            'is_sso' => 'boolean',
        ];
    }

    /**
     * "Enrolled" is either the demo flag (a seeded user we want treated as
     * enrolled, without a real TOTP secret so it can still log in challenge-free)
     * OR a real Fortify enrolment — so completing the flow on Nova's User Security
     * page actually takes effect. A production User would just use Fortify's trait
     * method as-is.
     */
    public function hasEnabledTwoFactorAuthentication(): bool
    {
        return (bool) $this->two_factor_enabled
            || (! is_null($this->two_factor_secret) && ! is_null($this->two_factor_confirmed_at));
    }
}
