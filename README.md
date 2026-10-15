# REST API Example

Slim 4 REST API with token authentication, email verification, CORS, and Redis-backed rate limiting.

## Setup

Requirements: PHP 8.3+, Composer, and Redis.

```sh
composer install
cp .env.dist .env
composer db:fresh
composer db:seed
php -S localhost:8000 -t public
```

The local environment uses SQLite at `db/db-local.sqlite3`. Seeding replaces the sample users and role/permission assignments. Configure Redis and other settings in `.env`. In the local environment, sent emails are written as `.eml` files to `logs/mail`; other environments use the transport configured by `MAILER_DSN`.

## Tests

Run the test suite with Pest:

```sh
composer test
```

## Endpoints

- `GET /health`
- `POST /auth/register` with `{"email":"user@example.com","password":"...","first_name":"...","last_name":"..."}`
- `POST /auth/login`, `POST /auth/logout`, `POST /auth/logout-all`
- `POST /email-verification/send` — authenticated
- `POST /email-verification/verify` with `{"token":"<token from email>"}`
- `POST /password/change` with `{"current_password":"...","new_password":"...","new_password_repeat":"..."}` — authenticated
- `POST /password/forgot` with `{"email":"user@example.com"}`
- `POST /password/reset` with `{"token":"<token from email>","new_password":"...","new_password_repeat":"..."}`
- `/users/{id}` — authenticated: `GET` to view, `POST` or `PATCH` to update, and `DELETE` to delete

Send the access token returned by login as a bearer token in the `Authorization` header for protected endpoints: `Authorization: Bearer <access_token>`.

New users must verify their email before login. Verification tokens expire according to `EMAIL_VERIFICATION_TTL`; requesting a replacement token invalidates earlier tokens. Changing a user's email address requires verification again and revokes that user's existing access tokens. The email-verification send endpoint returns `404` when the account does not exist and `409` when its email address is already verified.

Changing a password requires the current password, is limited to once every 24 hours, and revokes all of the user's existing access tokens. Email-verification and password-reset tokens expire according to `EMAIL_VERIFICATION_TTL` and `PASSWORD_RESET_TTL`, respectively; requesting a replacement token invalidates earlier tokens. Password-reset requests return `404` when the account does not exist. Resetting a password consumes the token and revokes all of the user's existing access tokens. In the local environment, issued verification and password-reset tokens are also included in their responses.

Database migrations and seeders are managed with Phinx. Set `APP_ENV=production` and configure the `MYSQL_*` variables in `.env` to use MySQL.
