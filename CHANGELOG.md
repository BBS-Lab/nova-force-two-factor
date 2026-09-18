# Changelog

All notable changes to `bbs-lab/nova-force-two-factor` will be documented in this file.

## v1.0.0 - 2026-09-18

First stable release of **Nova Force Two Factor** — make [Laravel Nova](https://nova.laravel.com)'s built-in two-factor authentication **mandatory**. A middleware redirects any admin who has not enrolled to the User Security page — with a toast (via [`bbs-lab/nova-toast`](https://github.com/BBS-Lab/nova-toast)) explaining why — until they set it up.

### ✨ Features

- **Mandatory 2FA enrolment** — an authenticated admin who has not enabled Nova's Fortify 2FA is sent to the User Security page until they do.
- **Correct per-request handling** — full-page navigation → `302`; Inertia visit → `409` + `X-Inertia-Location`; background XHR → reads pass through, writes are blocked with `403` (never breaks the SPA or enrolment itself).
- **Anti-lockout by design** — Nova's assets, logout and the whole User Security enrolment subtree are always allowed and cannot be blocked.
- **Configurable & toggleable** — extra `except.routes` / `except.paths` allow-lists (both wildcard-aware), an `NOVA_FORCE_TWO_FACTOR` switch, and auto-registration into Nova's stack (or wire the middleware yourself).

### ✅ Quality

- **100% line coverage**, mutation tested, PHPStan level 8, Pint.
- Verified in CI on **Nova 5** (Laravel 11/12/13, PHP 8.3/8.4/8.5), pre-flighted locally with `act`, and proven end-to-end with Playwright — every redirect path plus a full live 2FA enrolment (no enrolment endpoint is blocked).

### 📦 Requirements

PHP `^8.2` · Laravel Nova `^5.0` · Laravel `^11.0 || ^12.0 || ^13.0`

> Nova's built-in User Security / 2FA page is **Nova 5 only**. Enable it with `Nova::fortify()` +
`Features::twoFactorAuthentication()`, and give your Nova user Fortify's `TwoFactorAuthenticatable` trait —
the middleware reads its `hasEnabledTwoFactorAuthentication()`.
