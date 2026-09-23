<?php

declare(strict_types=1);

namespace Workbench\Database\Seeders;

use Carbon\CarbonInterface;
use Illuminate\Database\Seeder;
use Workbench\App\Models\User;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed one user for every combination of 2FA enrolment and password-rotation
     * state, so `composer serve` can demonstrate the force-2FA ⇄ password-rotation
     * interaction end-to-end (all passwords are "password"):
     *
     * - 2fa-valid@example.com    → enrolled, fresh password: reaches Nova normally.
     * - no2fa-valid@example.com  → not enrolled, fresh password: forced to enrol in 2FA.
     * - 2fa-expired@example.com  → enrolled, expired password: forced to change it.
     * - no2fa-expired@example.com→ not enrolled AND expired: funneled to the change screen first.
     * - sso@example.com          → not enrolled, but flagged is_sso: the
     *   ForceTwoFactor::bypass() callback (see NovaServiceProvider) lets it reach
     *   Nova without being forced to enrol, unlike no2fa-valid@example.com above.
     */
    public function run(): void
    {
        $this->seed('TwoFA + valid', '2fa-valid@example.com', twoFactor: true, changedAt: now());
        $this->seed('No 2FA + valid', 'no2fa-valid@example.com', twoFactor: false, changedAt: now());
        $this->seed('TwoFA + expired', '2fa-expired@example.com', twoFactor: true, changedAt: now()->subDays(200));
        $this->seed('No 2FA + expired', 'no2fa-expired@example.com', twoFactor: false, changedAt: now()->subDays(200));
        $this->seed('SSO admin', 'sso@example.com', twoFactor: false, changedAt: now(), isSso: true);
    }

    /**
     * Create the user (if missing), then set its state without triggering the
     * RotatesPassword model hooks, so the seeded timestamp stays as given.
     */
    private function seed(string $name, string $email, bool $twoFactor, CarbonInterface $changedAt, bool $isSso = false): void
    {
        $user = User::query()->firstOrCreate(
            ['email' => $email],
            ['name' => $name, 'password' => 'password'],
        );

        $user->forceFill([
            'two_factor_enabled' => $twoFactor,
            'password_changed_at' => $changedAt,
            'is_sso' => $isSso,
        ])->saveQuietly();
    }
}
