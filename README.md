# CPUT Faculty of Health & Wellness Sciences — Transport Request Platform

A Laravel app for managing student clinical-placement transport requests: students submit trip requests, staff approve them and bulk-onboard student accounts, and admins consolidate trips, combine nearby ones into shared journeys, generate RFQs, and track placements on a map.

## Stack

- Laravel 12, PHP 8.4 (via [Herd](https://herd.laravel.com))
- **MySQL** for storage (see setup below)
- Leaflet/OpenStreetMap for the placements map — no external API key required

## Local setup

Requires [Herd](https://herd.laravel.com) (or any PHP 8.2+/Composer setup) and MySQL.

### 1. Install and start MySQL

```bash
brew install mysql
brew services start mysql   # starts now and on every login
```

### 2. Create the database and app user

```bash
mysql -u root <<'SQL'
CREATE DATABASE transport_herd CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'transport_herd'@'localhost' IDENTIFIED BY 'choose-a-password';
GRANT ALL PRIVILEGES ON transport_herd.* TO 'transport_herd'@'localhost';
FLUSH PRIVILEGES;
SQL
```

### 3. Configure and migrate

```bash
composer install
cp .env.example .env
php artisan key:generate
# edit .env: set DB_PASSWORD to the password you chose above
php artisan migrate --seed
```

### 4. Serve it

If this folder is parked under Herd (e.g. `~/Herd/transport-herd`), it's already served at `https://transport-herd.test`. Otherwise:

```bash
php artisan serve
```

## Demo accounts (from the seeder)

| Role    | Email                     | Password    |
|---------|----------------------------|-------------|
| Admin   | admin@cput.ac.za          | admin123    |
| Staff   | staff@cput.ac.za          | staff123    |
| Student | student@mycput.ac.za      | student123  |

Students can also self-register from the login page. Staff can bulk-create student accounts under **Staff → Bulk Upload Students** with temporary passwords students must change on first login.

## Running tests

Tests run against an isolated in-memory SQLite database (configured in `phpunit.xml`), independent of the MySQL database used for the app itself:

```bash
php artisan test
```

## Key features

- **Student**: submit transport requests (clinical site, date, time, department, qualification); pickup is always CPUT Bellville Campus.
- **Staff**: approve/reject requests, bulk-upload trips or student accounts via CSV.
- **Admin**:
  - Consolidate/finalise trips, generate RFQs in the HG Travelling Services invoice format
  - Manage the clinical site directory (name, address, coordinates)
  - **AI Trip Planner** — recommends combining separate trips into one multi-stop journey when their clinical sites are close together (deterministic distance-clustering, not a hosted AI model — see `App\Support\JourneyPlanner`)
  - **Placements map** — Leaflet map of where students are placed, filterable by department/date/shift
  - **Dashboard** — department/qualification usage stats, CSV export

## Notes

- `AUTH_SECRET`-equivalent here is Laravel's `APP_KEY`, generated via `php artisan key:generate` — treat it as a secret, especially in production.
- Session/cache/queue all use the `database` driver, so they persist in MySQL alongside app data.

## Deploying to Render

This repo deploys as a Docker web service + a managed **PostgreSQL** database (Render has no managed MySQL — production runs Postgres; your local dev machine can keep using MySQL, since Eloquent doesn't care which engine each environment uses). Everything's already in the repo:

- `Dockerfile` — multi-stage build: Node stage builds front-end assets, PHP stage (`php:8.3-apache`) serves `public/` with `pdo_pgsql`/`pdo_mysql` both installed.
- `docker/entrypoint.sh` — on every boot: waits for the database, runs `php artisan migrate --force`, caches config/routes/views, links storage, then starts Apache.
- `render.yaml` — a Render **Blueprint**: one web service + one free Postgres database, with `DB_*` env vars wired automatically from the database to the web service.

### Steps

1. Generate a real app key locally (don't reuse a dev one):
   ```bash
   php artisan key:generate --show
   ```
   Copy the `base64:...` output.

2. In the [Render dashboard](https://dashboard.render.com): **New → Blueprint**, connect this GitHub repo (`meyerjo2024/FHWSTransport`), and let it read `render.yaml`.

3. When prompted for the `APP_KEY` env var (it's marked `sync: false` in the blueprint so it's never stored in git), paste the value from step 1.

4. Deploy. Render builds the Docker image, provisions Postgres, links the two, and the entrypoint migrates the database automatically on first boot.

5. **Seed demo/initial data** (optional, one-time) via Render's shell for the web service:
   ```bash
   php artisan db:seed --force
   ```

### What I verified locally before recommending this

- Ran the full migration set (all 12 migrations) against a real local PostgreSQL instance — clean, no MySQL-specific syntax anywhere in the schema.
- Built the actual `Dockerfile` with `docker build` and ran it as a container against a containerized Postgres, reproducing Render's setup: migrations ran automatically on boot, `/up` health check returned 200, `/login` rendered, and logging in as admin and browsing to Clinical Sites correctly showed all 68 seeded sites — so this isn't just a theoretical config, the whole path has actually been exercised end-to-end.

## Production hardening

- Serve over HTTPS only (Render provides TLS). Set `APP_ENV=production`, `APP_DEBUG=false` and an `https://` `APP_URL`; HTTPS is forced and HSTS is sent automatically in production.
- Demo accounts (`admin123` / `staff123` / `student123`) are **not** seeded in production. Create a real admin with a strong password; set `SEED_DEMO_USERS=true` only if you deliberately want them.
- API tokens expire after `SANCTUM_TOKEN_EXPIRATION` minutes (default 30 days) and expired tokens are pruned daily (run the Laravel scheduler). Changing a password signs out every other API session.
- Set `SESSION_SECURE_COOKIE=true` behind HTTPS. Student calendar-feed links are secrets in the URL, so they must only be used over HTTPS; students can reset them in the app.
- Local HTTPS with Herd: `herd secure fhws-transport`, then set `APP_URL=https://fhws-transport.test`.
