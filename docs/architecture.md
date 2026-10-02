# Ledger accounting SaaS

## Reference analysis

The supplied September 30 ERD is the primary schema reference. It defines companies/settings, users/memberships/roles, separate customers and suppliers, invoices/items/payments/credit notes, bills/items/expenses/categories, accounts/journals/lines, banks/transactions/reconciliations, currencies/taxes/attachments, audit/financial years and subscriptions/plans/payment methods. No separate interface reference was supplied.

The drawing's direct accounts → journal_entries relationship is represented through journal_lines. The apparent customers → suppliers connector is not a business relationship: both independently belong to companies. Invoice credit notes are one-to-many, allowing partial adjustments. Tenant-owned children also receive company_id and composite foreign keys to prevent cross-company references at the database boundary.

## Decisions

* Shared PostgreSQL database, explicit active-company context, fail-closed Eloquent scopes, membership middleware, granular permission checks, and composite foreign keys. Background workers must explicitly establish and clear company context.
* Modular monolith. Actions/services own transactions; controllers validate and delegate.
* Monetary calculations use BCMath decimal strings, never floating point. Base-currency posting only initially; foreign-currency documents must be rejected until exchange gain/loss accounting is implemented.
* Accrual accounting. Invoice approval debits receivables and credits sales/output tax. Bill approval debits purchases/input tax and credits payables. Receipts/payments settle control accounts through a bank account. Expenses post directly to expense/input tax and bank, and are not also entered as bills.
* Posted journals and document amounts are immutable. Adjustments use reversal entries. Lock dates prohibit backdated postings and reversals. Audit records are append-only at application and PostgreSQL boundaries.
* Subscription billing belongs to the SaaS platform, separate from tenant journals.
* Proposed additional schema: permissions/role_permissions, invitations, products, bill payments, credit note items, refunds, exchange rates, reconciliation sessions, subscription invoices/payments, journal reversal links, accounting locks, payment idempotency keys and company document sequences.

## Delivery status

See `docs/progress.md` for verified features and remaining work. A table or navigation entry alone does not establish that a workflow is implemented.
