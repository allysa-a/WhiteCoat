# Deployment Guide

## 1) Server requirements

- PHP `8.1+`
- Extensions: `pdo_mysql`, `mbstring`, `json`, `fileinfo`, `openssl`
- Apache with `mod_rewrite` enabled
- MySQL/MariaDB database

## 2) Environment setup

1. Copy `.env.example` to `.env`.
2. Set production values:
   - `APP_ENV=production`
   - `APP_DEBUG=false`
   - `JWT_SECRET=<strong-random-secret>`
   - `DB_HOST`, `DB_USER`, `DB_PASSWORD`, `DB_NAME`
   - `CORS_ALLOW_ORIGIN=<your frontend origin>`
   - `FRONTEND_URL=<your frontend URL>` (used for verification redirect)
   - `GOOGLE_FORM_RESPONSES_URL=<your Google Form responses feed URL>` (used by Notifications page)
   - SMTP values for email verification: `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_ENCRYPTION`, `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME`

## 3) Install backend dependencies

From project root:

```bash
composer install --no-dev --optimize-autoloader
```

If Composer reports TLS/OpenSSL errors, enable PHP `openssl` extension for the CLI PHP used by Composer.

## 4) Build frontend into root (for Apache/PHP hosting)

From `frontend` folder:

```bash
npm ci
npm run build:root
```

This writes production `index.html` + `assets/*` into the project root so `index.php` can serve the SPA and API from the same host.

## 5) File permissions

- Ensure `uploads/` is writable by the web server user.

## 6) Apache/site checks

- Keep `.htaccess` in project root.
- Point virtual host/document root to this folder.
- Verify rewrite is active (`AllowOverride All` for this directory).

## 7) Smoke test

- Open app root URL in browser.
- Test login.
- Create one record and verify it appears in history.
- Verify delete action works.
- Download analytics export.
