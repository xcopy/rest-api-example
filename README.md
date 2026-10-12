# REST API Example

Slim 4 REST API with token authentication, email verification, role-based access control, CORS, and Redis-backed rate limiting.

## Setup

Requirements: PHP 8+, Composer, and Redis.

```sh
composer install
cp .env.dist .env
composer db:fresh
composer db:seed
php -S localhost:8000 -t public
```

The local environment uses SQLite at `db/db.sqlite3`. Seeding replaces the sample users and role/permission assignments. Configure Redis and other settings in `.env`. In the local environment, sent emails are written as `.eml` files to `logs/mail`; other environments use the transport configured by `MAILER_DSN`.

## Endpoints

- `POST /auth/login`, `POST /auth/logout`, `POST /auth/logout-all`
- `POST /auth/change-password` with `{"current_password":"...","new_password":"...","new_password_repeat":"..."}`
- `POST /auth/forgot-password` with `{"email":"user@example.com"}`
- `POST /auth/reset-password` with `{"token":"<token from email>","new_password":"...","new_password_repeat":"..."}`
- `POST /auth/verify-email` with `{"token":"<token from email>"}`
- `POST /auth/resend-verification` with `{"email":"user@example.com"}`
- `/users` — authenticated, permission-protected user CRUD

Use the access token returned by login as a Bearer token for protected endpoints.

New users must verify their email before login. Verification tokens expire according to `AUTH_EMAIL_VERIFICATION_TTL`; requesting a replacement token invalidates earlier tokens. Changing a user's email address requires verification again and revokes that user's existing access tokens. The resend endpoint returns `404` when the account does not exist and `422` when its email address is already verified.
Changing a password requires the current password, is limited to once every 24 hours, and revokes all of the user's existing access tokens.
In production, password reset requests return the same response whether or not an account exists. Reset tokens expire according to `AUTH_PASSWORD_RESET_TTL`, and requesting a replacement token invalidates earlier tokens. Resetting a password consumes the token and revokes all of the user's existing access tokens. In the local environment, the reset token is also included in the response when an account exists.

Database migrations and seeders are managed with Phinx. Set `APP_ENV=production` and configure the `MYSQL_*` variables in `.env` to use MySQL.
