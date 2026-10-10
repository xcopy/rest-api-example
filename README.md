# REST API Example

Slim 4 REST API with token authentication, role-based access control, CORS, and Redis-backed rate limiting.

## Setup

Requirements: PHP 8+, Composer, and Redis.

```sh
composer install
cp .env.dist .env
composer db:fresh
composer db:seed
php -S localhost:8000 -t public
```

The local environment uses SQLite at `db/db.sqlite3`. Seeding replaces the sample users and role/permission assignments. Configure Redis and other settings in `.env`.

## Endpoints

- `POST /auth/login`, `POST /auth/logout`, `POST /auth/logout-all`
- `/users` — authenticated, permission-protected user CRUD

Use the access token returned by login as a Bearer token for protected endpoints.

Database migrations and seeders are managed with Phinx. Set `APP_ENV=production` and configure the `MYSQL_*` variables in `.env` to use MySQL.
