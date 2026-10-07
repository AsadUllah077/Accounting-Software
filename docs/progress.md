# Implementation progress

Repository inspection: empty directory, no existing code or dependencies to preserve. Host PHP 8.2.4 includes BCMath and PostgreSQL drivers. Node 24 is available; npm must be invoked through its npm-cli.js because the host PowerShell launcher is misconfigured. Docker is absent.

The application is named **Ledger**. It is a functional modular monolith backed by PostgreSQL, with connected accounting workflows rather than disconnected screens. This document distinguishes delivered functionality from the remaining scope in the original brief. It is not a certification of production readiness.

**Current local configuration:** the user's requested testing setup now uses SQLite (`database/database.sqlite`), including database-backed sessions, cache and queues. Automated tests use a separate in-memory SQLite database. PostgreSQL remains supported for later connection and deployment; previous local settings are preserved in ignored `.env.postgres.local` and existing PostgreSQL records are retained. SQLite migrations/seeders and browser verification are rerun after this switch. PostgreSQL-only immutable/balanced posting triggers are additional safeguards when PostgreSQL is connected; application-level accounting and tenant checks continue to apply with SQLite.

## Phase 1 — Foundation

The follow-up user-management implementation adds a dedicated **Settings → Users & roles** page at `/settings/users`: direct new-account creation with password hashing and email verification, existing/new-user invitations, invitation revocation, server-paginated member search, role assignment, activation/deactivation and company-specific role permission editing. Reactivation and creation enforce member limits; the last Owner is protected. Role-aware company navigation opens an accessible feature for Employees and Sales Managers. See `docs/user-management.md` for operation and exact boundaries. The dedicated user-management Chrome check passes creation, role edits, deactivation, persisted permission changes and mobile overflow checks.

Implemented Laravel 12, Vue 3, Inertia, TypeScript, Tailwind and Vite with locked dependencies. Breeze supplies registration, login, logout, password reset, verification and profile/password management. Active-company middleware checks authenticated membership; tenant models fail closed without company context. Companies have their own chart, bank, tax settings, numbering sequences and financial year. Roles, granular permissions, company role overrides and secure invitation acceptance are implemented.

Files: `app/Services/Tenancy/` (including `CompanyPermissions.php`), `app/Http/Middleware/`, `app/Policies/DocumentPolicy.php`, `app/Models/TenantModel.php`, `routes/auth.php`, `routes/accounting.php`, `database/seeders/FoundationSeeder.php`, `.env.example`, `composer.json`, `package.json` and lockfiles.

Migrations preserve every named ERD core table and add supporting integrity tables. PostgreSQL composite foreign keys prevent cross-company parent/child associations. Foundation seeding creates roles, permissions, currencies and subscription plans. Demo seeding creates actual documents and journals through business services, and is restricted to development.

## Phase 2 — Interface

Implemented a responsive teal/slate accounting layout with mobile navigation, company switcher, user menu, breadcrumbs, permission-aware navigation, server-backed tables, forms, badges, charts, validation and flash messages. The dashboard reads posted journals, balances and company documents. Date presets, a financial-year selector, empty states and responsive desktop/mobile layouts are included.

Files: `resources/js/Layouts/AppLayout.vue`, `resources/js/Components/`, `resources/js/Composables/`, `resources/js/types/accounting.ts`, `resources/js/Pages/Dashboard.vue`, `app/Http/Controllers/DashboardController.php`.

The supplied attachment is an ERD, not a UI screenshot. No additional screenshot was supplied, so the interface uses a documented professional accounting style. There is no dark mode, desktop sidebar collapse control or notification inbox yet. Screenshots of the running application are in `docs/screenshots/`.

## Phase 3 — Contacts and business records

Implemented customers and suppliers: create, edit, archive, search, pagination, document history, posted outstanding balance, statements, atomic validated CSV imports and XLSX exports. Chart of accounts supports create/edit, hierarchy validation, activation and cash-flow classification; system controls and account types with history are protected. Tax rates and non-overlapping financial years can be created. Period locks and year closure prohibit backdated posting.

Files: `app/Http/Controllers/ContactController.php`, `ContactImportController.php`, `AccountingController.php`, `SettingsController.php`, `app/Services/Accounting/AccountService.php`, `resources/js/Pages/Contacts/`, `Accounting/Accounts.vue`, `Settings/Index.vue`.

Currencies are seeded reference data; transactions use a company's base currency. Product/service and exchange-rate tables and models exist, but a product catalog and foreign-currency accounting workflow are not implemented. Financial-year closure locks periods; it does not generate a separate retained-earnings closing journal. Accumulated earnings are calculated in the balance sheet.

## Phase 4 — Sales and purchases

Implemented numbered invoices and bills with draft editing, decimal quantities/prices, fixed line discounts, snapshotted taxes, approval and balanced posting. Posted financial values cannot be edited through ordinary workflows. Unpaid/uncredited documents can be voided through a reversal. Partial/full customer receipts and supplier payments update outstanding balances and post once using idempotency keys. Credit-note items and customer refunds integrate with the ledger. PDFs, XLSX exports, initial queued invoice email and private attachments are implemented.

Paid expenses post expense/input-tax/bank entries, with retry protection, search, detail/history, private receipts and linked journals. They cannot be entered as a second payment against an existing bill. Categories support nested creation through a protected endpoint; category management has no dedicated frontend editor yet. Expense draft/approval/edit/reversal workflows are not enabled. Payment-method reference data has no management screen. Supplier payments allocate to one bill per operation, rather than a multi-bill payment batch. Discounts are fixed amounts; percentage discounts are not enabled. Overdue state is derived from due dates and balances, rather than a scheduled persisted status. Invoice email resending after the initial send is not implemented.

Files: `app/Services/Invoicing/DocumentService.php`, `CreditService.php`, `app/Services/Accounting/ExpenseService.php`, `app/Http/Requests/`, `app/Jobs/SendInvoice.php`, `resources/js/Pages/Documents/`, `Purchases/`, `resources/views/pdf/`.

## Phase 5 — Accounting engine

Implemented centralized transactional posting, same-company active-account validation, at least two lines, one positive debit or credit per line and exact balance checks using BCMath. Manual journals post through permission-controlled validation; explicit one-time reversals create opposite entries. Original entries remain posted to preserve history. Automatic journals must be adjusted through their originating workflow. Opening balances can be entered as balanced manual journals. Reports read posted journal lines.

PostgreSQL triggers additionally reject unbalanced posting and mutation/deletion of posted journals or lines. Audit records are immutable. Posting takes a company lock to serialize with period closure. Database checks supplement application validation. Manual journals currently post directly; a separate draft/approver workflow is not implemented.

Files: `app/Services/Accounting/JournalService.php`, `app/Models/JournalEntry.php`, `JournalLine.php`, `Concerns/`, `resources/js/Pages/Accounting/Journals.vue`, `database/migrations/2026_10_01_000200_add_adjustment_integrity.php`.

## Phase 6 — Banking

Implemented multiple base-currency bank accounts linked to asset accounts, validated CSV statement imports, duplicate fingerprints, imported transactions, signed exact matching against posted bank journal lines, and reconciliation history. Transactions and journal lines cannot be matched twice. Statement sessions can be opened and closed only when the statement balance agrees with the ledger and eligible imports are matched.

Files: `app/Services/Banking/`, `app/Http/Controllers/BankingController.php`, `BankAccountController.php`, `ReconciliationSessionController.php`, `resources/js/Pages/Banking/Index.vue`, `Components/BankSetup.vue`.

CSV columns are `date,description,amount,reference`. Identical rows without distinct references are treated as duplicates; use distinct references for legitimate identical transactions. Matching is one-to-one for whole lines. External feeds, split/group matching, reconciliation undo and independent session allocation of matches remain unimplemented. Session validation currently certifies the bank's complete eligible statement history through its ending date.

## Phase 7 — Reporting and audit

Implemented profit and loss, balance sheet, trial balance, general ledger/account transactions, receivable/payable aging, customer/supplier statements, expense summary, tax summary and direct cash flow. Reports use posted accounting records; statements include reversals and aging respects the requested as-of date. Cash flow excludes internal bank transfers and proportionally classifies supplier settlements using underlying purchase lines. Users can assign operating/investing/financing classifications to accounts. These are operational reports; statutory/local tax formats and a configurable cash-flow accounting policy require further work.

Implemented date/account filters, print-friendly PDF and XLSX downloads, formula-safe spreadsheet cells, and background large-ledger exports with private, user/company-authorized downloads. Audit logs capture financial, reconciliation, company and permission activity and have a protected viewer.

Files: `app/Services/Reporting/`, `app/Services/Audit/`, `app/Exports/`, `app/Jobs/GenerateReportExport.php`, `app/Http/Controllers/ReportController.php`, `ReportExportController.php`, `resources/js/Pages/Reports/`, `Settings/Audit.vue`.

General-ledger PDFs/XLSX can run in the queue. Other reports are synchronous; load testing and further streaming of very large financial/aging reports remain necessary. Trial balance displays movements for the selected period; opening balances are available in the ledger and balance sheet. Search/sort/filter controls are not universal on every table.

## Phase 8 — Subscriptions

Implemented platform-managed plans/features/limits, company trial subscriptions, plan changes, cancellations, billing history, separate subscription invoices and manually verified platform-admin payment recording. New companies receive a 14-day trial when a plan is available. Invoice and member limits are enforced by the backend. Scheduled renewal creates unique period invoices and handles past-due status. Subscription billing is separate from tenant accounting journals.

Files: `app/Services/Subscriptions/`, `app/Http/Controllers/SubscriptionController.php`, `PlanController.php`, `resources/js/Pages/Settings/Subscription.vue`, `Plans.vue`, `routes/console.php`, `app/Console/Commands/`.

Plan changes start a new period at the selected full price; proration is deliberately not implemented. There is no external card gateway, recurring auto-charge or webhook integration. These require a chosen provider and provider-specific idempotency/verification. A tenant owner cannot self-grant platform administration; use the trusted-server `ledger:admin` command.

## Phase 9 — Verification and deployment preparation

The last PostgreSQL verification, before the user-management follow-up, passed **98 tests / 226 assertions**, covering auth, tenant isolation, permissions, numbering/totals/taxes, payments, credits/refunds, immutability, reversals, period locks, reports, imports, reconciliation, subscriptions, private attachments, export jobs and expense idempotency. After the user-management follow-up, the default SQLite suite passes **105 tests / 275 assertions**, with 3 explicitly skipped PostgreSQL safeguards. The frontend passes TypeScript checking and Vite production build. Pint formats PHP. Installed Chrome is used for real navigation, contact creation, invoice approval, expense posting/detail access and desktop/mobile overflow checks; the current Chrome check passes, with no uncaught JavaScript errors or mobile document overflow.

Files: `tests/Feature/`, `phpunit.postgres.xml`, `tools/browser-check.mjs`, `tools/setup-postgres.ps1`, `Dockerfile`, `compose.yaml`, `docker/`, `docs/deployment.md`, `docs/schema.md`.

Docker deployment, Redis/Horizon on Linux, live SMTP and remote S3 were not executed because those services are not provisioned on this host. Local queues use PostgreSQL and development email is logged. Docker runtime, backups/restores, load/concurrency testing, accessibility review, jurisdiction-specific accounting review and a restricted production DB role must be validated before production launch. PostgreSQL RLS is not enabled; tenant scopes, authenticated membership, authorization and composite foreign keys provide the implemented isolation. The development database cluster role must not be used in production.

Factories are not provided for every ERD table; the original user factory and service-driven test/seed fixtures are used. Dedicated lifecycle events/listeners and comprehensive notification delivery remain additional scope. The complete original brief is therefore not represented as fully finished.

## Migration inventory

* Laravel base migrations: users/auth, cache and jobs.
* `000100`: original ERD plus permissions, invitations, products, adjustments, exchange rates, reconciliation sessions, subscription billing and accounting locks/sequences.
* `000200`: adjustment integrity, PostgreSQL checks and immutable balanced-posting/audit triggers.
* `000300`: cash-flow classifications.
* `000400`: platform administration and subscription period/lifecycle constraints.
* `000500`: private background report exports.
* `000600`: company-specific permission overrides.
* `000700`: company-unique expense idempotency and payload hashes.

All have been applied to the local PostgreSQL database. `php artisan ledger:erd` regenerates the diagram and full column/constraint catalog from the implemented schema. A source-file inventory is in `docs/files.md`.
