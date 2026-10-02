# Ledger — multi-company accounting

Laravel 12, Vue 3, Inertia, TypeScript and Tailwind accounting application built from the supplied ERD. SQLite is configured for local testing, with PostgreSQL support ready for later connection. Financial workflows use BCMath, transactional double-entry posting, tenant isolation, period locks and immutable posted journals.

The running local application is available at **http://127.0.0.1:8087**. Register an account, verify its email using the local development mail log, and create a company. Each new company receives its own chart, bank, tax settings, document numbering and financial year. Local email is logged; start a queue worker for invoice emails and larger report exports.

## Setup

Requirements: PHP 8.3 recommended, PDO SQLite, Composer, Node and the PHP extensions listed in [deployment instructions](docs/deployment.md). Local development and automated tests use SQLite by default. PostgreSQL can be connected later. Windows development works on the installed PHP 8.2; Horizon runs on Linux.

```sh
composer install --ignore-platform-req=ext-pcntl --ignore-platform-req=ext-posix
npm ci
cp .env.example .env
php artisan key:generate
php -r "file_exists('database/database.sqlite') || touch('database/database.sqlite');"
php artisan migrate --seed
npm run build
php artisan serve --host=127.0.0.1 --port=8087
php artisan queue:work database --sleep=1 --tries=1 --timeout=360 --memory=512
```

Optional local seed data: `php artisan db:seed --class=DemoSeeder`. It creates actual accounting transactions and prints a random password once. It is unavailable in production and does not reset an existing account.

This workspace now uses `database/database.sqlite`. Previous PostgreSQL settings are preserved in ignored `.env.postgres.local`, and the existing PostgreSQL records are retained. See the deployment guide to connect your PostgreSQL installation later. On this machine invoke npm using `node "C:\Program Files\nodejs\node_modules\npm\bin\npm-cli.js"` because the host npm launcher is broken.

## Verification

```sh
php artisan test
npm run build
php vendor/bin/pint --test app database routes tests
php artisan ledger:erd
```

The default suite uses isolated in-memory SQLite. After connecting PostgreSQL, its optional suite is `php vendor/bin/pest --configuration=phpunit.postgres.xml --compact`, using a dedicated `ledger_test` database. It refreshes that database and tests PostgreSQL constraints/triggers; never configure it against a company database. Optional Chrome workflow verification is documented in the deployment guide.

## Documentation

* [Implemented phases, tests and remaining scope](docs/progress.md)
* [ERD analysis and accounting design decisions](docs/architecture.md)
* [Generated schema diagram and column catalog](docs/schema.md)
* [Installation, Docker and production operations](docs/deployment.md)
* [Source-file inventory](docs/files.md)
* [Desktop screenshot](docs/screenshots/dashboard-desktop.png) and [mobile screenshot](docs/screenshots/dashboard-mobile.png)

The app includes connected contacts, invoices, bills, receipts/payments, credits/refunds, paid expenses, chart/manual journals/reversals, bank CSV reconciliation, financial reports, private attachments, audits and subscription administration. The original brief still has additional scope: foreign-currency settlement, gateway auto-collection, split reconciliation, some management/approval screens, universal table controls and broader infrastructure verification. See the phase report for precise boundaries; this project is not presented as fully production certified.
