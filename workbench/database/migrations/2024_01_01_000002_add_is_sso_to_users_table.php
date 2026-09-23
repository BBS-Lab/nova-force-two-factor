<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            // Demo flag: admins provisioned through SSO, whose second factor is
            // handled by the identity provider and must skip local 2FA enrolment.
            // Read by the ForceTwoFactor::bypass() callback in the NovaServiceProvider.
            $table->boolean('is_sso')->default(false)->after('password');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('is_sso');
        });
    }
};
