# Installation and deployment

## Local development

Requirements: PHP 8.3 recommended (Laravel supports the installed 8.2), BCMath, PDO SQLite, DOM/XML, ZIP, GD, mbstring, OpenSSL; Composer 2; Node 24. SQLite is the default for local development and tests. PDO PostgreSQL and a PostgreSQL server are needed only when connecting PostgreSQL later. Horizon requires Linux with PCNTL/POSIX. On Windows use the database queue worker for local development.

```sh
composer install --ignore-platform-req=ext-pcntl --ignore-platform-req=ext-posix
npm ci
cp .env.example .env
php artisan key:generate
```

The default `.env.example` uses `DB_CONNECTION=sqlite`, `DB_DATABASE=database/database.sqlite` and enabled foreign keys. Create the database file if it does not exist, then migrate:

```sh
php -r "file_exists('database/database.sqlite') || touch('database/database.sqlite');"
php artisan migrate --seed
php artisan db:seed --class=DemoSeeder
npm run build
php artisan serve --host=127.0.0.1 --port=8087
php artisan queue:work database --sleep=1 --tries=1 --timeout=360 --memory=512
```

The demo seeder prints a randomly generated password once. It is local/test-only and does not reset existing logins. Register normally to use an empty company. The development mailer logs verification/reset links to `storage/logs/laravel.log`; configure SMTP for delivery. Invoice emails require a running worker. Never use demo data or the logged mailer in production.

On this Windows machine the npm launcher is misconfigured. Use `node "C:\Program Files\nodejs\node_modules\npm\bin\npm-cli.js" run build` or repair the host Node installation. The current app uses SQLite. Previous PostgreSQL settings are preserved privately in `.env.postgres.local`; its database records remain separate from the SQLite file. SQLite is suitable for this local testing setup; PostgreSQL remains the deployment target with additional database safeguards.

## Connect PostgreSQL later

After installing PostgreSQL and enabling PHP's PDO PostgreSQL extension, create the `ledger` database and an appropriate database user. Replace the active SQLite settings in `.env`:

```dotenv
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=ledger
DB_USERNAME=your_database_user
DB_PASSWORD=your_database_password
```

Run `php artisan optimize:clear` and `php artisan migrate --seed`, then restart the web server and queue worker. This initializes the selected PostgreSQL database. Transferring existing SQLite records requires a separate data-transfer step. For optional PostgreSQL tests, also create `ledger_test` and run `php vendor/bin/pest --configuration=phpunit.postgres.xml --compact`. The default `php artisan test` continues using isolated in-memory SQLite.

An optional isolated PostgreSQL cluster was provisioned earlier on this host; you do not need it for SQLite testing. `tools/setup-postgres.ps1` can provision a new local cluster but refuses to overwrite an existing one and changes `.env` to PostgreSQL. Do not run it while intending to use SQLite.

Start the existing project cluster with `pg_ctl -D "C:\Users\PMLS\Desktop\Accounting-Software\storage\postgres" -l "C:\Users\PMLS\Desktop\Accounting-Software\storage\postgres.log" start`. Stop that specific cluster with the same `-D` argument and `stop`. The existing system PostgreSQL service is separate.

## Tests and validation

```sh
php artisan test
npm run build
php vendor/bin/pint --test app database routes tests
php artisan ledger:erd
```

The default suite uses in-memory SQLite. Three PostgreSQL-only checks are explicitly skipped there. The optional PostgreSQL suite uses `ledger_test` with host/port/credentials from `.env` and refreshes that dedicated database. Never point the test configuration at a live company database. PostgreSQL tests cover triggers and composite foreign keys.

Optional browser verification: run `php tools/browser-fixture.php`, start the local server on port 8087, then `node tools/browser-check.mjs`. It uses installed Chrome and a dedicated local-only browser account; its credential file is ignored by Git. Screenshots go to `docs/screenshots/`.

## Docker

Docker Engine/Desktop must be installed separately; it is not available on the development host, so container startup has not been verified here. The images target PHP 8.3, PostgreSQL 17, Redis 7.4, Nginx and Node 24.

1. Copy `.env.example` to `.env`; replace the local SQLite settings with `DB_CONNECTION=pgsql`, `DB_DATABASE=ledger`, `DB_USERNAME=ledger` and your database password. Set a generated application key, application URL and mail credentials. Compose supplies the internal PostgreSQL host and port.
2. Run `docker compose up --build -d`.
3. Run `docker compose exec app php artisan migrate --seed --force`.
4. Visit `http://127.0.0.1:8080`. Horizon and the scheduler run as separate processes.

For S3 attachments, set `ATTACHMENTS_DISK=s3`, `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `AWS_DEFAULT_REGION`, `AWS_BUCKET`, `AWS_ENDPOINT` and `AWS_USE_PATH_STYLE_ENDPOINT=true` when your provider requires it. Buckets must be private. The optional `storage` Compose profile provides MinIO; supply separate MinIO credentials, create a private bucket, and use `http://minio:9000` as its internal endpoint. Do not embed storage credentials in Vue or commit `.env`.

## Production operation

* Set `APP_ENV=production`, `APP_DEBUG=false`, HTTPS `APP_URL`, secure cookies, a real mail transport, Redis queues/cache/sessions and private S3 storage. Terminate TLS at your ingress. Trust only your actual proxy addresses.
* Build assets and dependencies from lockfiles; run migrations as a deployment step. Run `php artisan optimize` after environment configuration, and `php artisan horizon:terminate` after code deployment so workers restart.
* `php artisan ledger:admin existing-user@example.com` grants platform administration through trusted server access. Tenant owners cannot self-grant it. `/horizon` requires platform administration even in local mode. `/admin/plans` manages plan pricing and limits.
* Schedule `php artisan schedule:run` once per minute, or use the Compose scheduler. `subscriptions:renew` creates billing invoices and handles past-due status; it does not charge cards. An external payment gateway/webhook integration is still required for automatic collection.
* Back up PostgreSQL and private objects, test restores, monitor failed jobs and mail delivery, and use a restricted application DB role. The development role created by `initdb` is a cluster administrator and must not be reused as the production runtime role.
* Journal triggers enforce balanced immutable posted entries, and audit triggers prohibit modification. Tenant scopes and composite foreign keys enforce application isolation. PostgreSQL row-level security is not enabled; introducing it requires transaction-scoped connection context and worker/pool integration.
* Reconciliation currently matches complete signed bank lines one-to-one. Split/group matching and external bank feeds are outside this implementation.
* Transactions currently post in the company's base currency. Exchange-rate storage exists; foreign-currency settlement, translation and exchange-gain/loss workflows remain disabled.

See `docs/progress.md` for exact implementation boundaries and `docs/architecture.md` for accounting decisions.
