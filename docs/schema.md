# Implemented database ERD

Generated from the migrated database using `php artisan ledger:erd`. Composite links include company_id.

```mermaid
erDiagram
    accounts {
        int8 id
        int8 company_id
        timestamp created_at
        timestamp updated_at
        varchar code
        varchar name
        varchar type
        int8 parent_id
        bool is_active
        varchar system_key
        varchar cash_flow_class
    }
    companies ||--o{ accounts : company_id
    accounts ||--o{ accounts : company_id_parent_id
    attachments {
        int8 id
        int8 company_id
        timestamp created_at
        timestamp updated_at
        varchar model_type
        int8 model_id
        varchar disk
        varchar file_path
        varchar file_name
        varchar mime_type
        int8 size
    }
    companies ||--o{ attachments : company_id
    audit_logs {
        int8 id
        int8 company_id
        int8 user_id
        varchar event
        varchar model_type
        int8 model_id
        json old_values
        json new_values
        timestamp created_at
    }
    companies ||--o{ audit_logs : company_id
    users ||--o{ audit_logs : user_id
    bank_accounts {
        int8 id
        int8 company_id
        timestamp created_at
        timestamp updated_at
        varchar name
        varchar bank_name
        varchar account_number
        varchar currency
        int8 account_id
        varchar linked_status
    }
    accounts ||--o{ bank_accounts : company_id_account_id
    companies ||--o{ bank_accounts : company_id
    bank_transactions {
        int8 id
        int8 company_id
        timestamp created_at
        timestamp updated_at
        int8 bank_account_id
        date date
        text description
        numeric amount
        varchar transaction_type
        varchar reference
        varchar import_hash
        timestamp imported_at
    }
    bank_accounts ||--o{ bank_transactions : company_id_bank_account_id
    companies ||--o{ bank_transactions : company_id
    bill_items {
        int8 id
        int8 company_id
        timestamp created_at
        timestamp updated_at
        int8 bill_id
        int8 account_id
        int8 tax_rate_id
        text description
        numeric quantity
        numeric unit_price
        numeric discount
        numeric tax_rate
        numeric subtotal
        numeric tax_amount
        numeric total
    }
    accounts ||--o{ bill_items : company_id_account_id
    bills ||--o{ bill_items : company_id_bill_id
    companies ||--o{ bill_items : company_id
    tax_rates ||--o{ bill_items : company_id_tax_rate_id
    bill_payments {
        int8 id
        int8 company_id
        timestamp created_at
        timestamp updated_at
        int8 bill_id
        int8 bank_account_id
        int8 journal_entry_id
        varchar payment_method
        numeric amount
        date paid_at
        varchar reference
        text notes
        varchar idempotency_key
        varchar request_hash
    }
    bank_accounts ||--o{ bill_payments : company_id_bank_account_id
    bills ||--o{ bill_payments : company_id_bill_id
    companies ||--o{ bill_payments : company_id
    journal_entries ||--o{ bill_payments : company_id_journal_entry_id
    bills {
        int8 id
        int8 company_id
        timestamp created_at
        timestamp updated_at
        int8 supplier_id
        varchar bill_number
        date bill_date
        date due_date
        varchar currency
        varchar status
        numeric subtotal
        numeric tax_total
        numeric total
        numeric paid_total
        numeric credited_total
        text notes
        int8 journal_entry_id
    }
    companies ||--o{ bills : company_id
    journal_entries ||--o{ bills : company_id_journal_entry_id
    suppliers ||--o{ bills : company_id_supplier_id
    companies {
        int8 id
        varchar name
        varchar legal_name
        varchar email
        varchar phone
        varchar currency
        varchar timezone
        date financial_year_start_date
        date locked_through
        varchar status
        timestamp created_at
        timestamp updated_at
    }
    currencies ||--o{ companies : currency
    company_invitations {
        int8 id
        int8 company_id
        timestamp created_at
        timestamp updated_at
        varchar email
        varchar token_hash
        int8 role_id
        timestamp expires_at
        timestamp accepted_at
    }
    companies ||--o{ company_invitations : company_id
    roles ||--o{ company_invitations : role_id
    company_permission_overrides {
        int8 id
        int8 company_id
        int8 role_id
        int8 permission_id
        bool allowed
        timestamp created_at
        timestamp updated_at
    }
    companies ||--o{ company_permission_overrides : company_id
    permissions ||--o{ company_permission_overrides : permission_id
    roles ||--o{ company_permission_overrides : role_id
    company_settings {
        int8 id
        int8 company_id
        timestamp created_at
        timestamp updated_at
        varchar setting_key
        text setting_value
    }
    companies ||--o{ company_settings : company_id
    company_users {
        int8 id
        int8 company_id
        timestamp created_at
        timestamp updated_at
        int8 user_id
        int8 role_id
        bool is_active
    }
    companies ||--o{ company_users : company_id
    roles ||--o{ company_users : role_id
    users ||--o{ company_users : user_id
    credit_note_items {
        int8 id
        int8 company_id
        timestamp created_at
        timestamp updated_at
        int8 credit_note_id
        int8 account_id
        text description
        numeric amount
        numeric tax_amount
        int8 invoice_item_id
    }
    accounts ||--o{ credit_note_items : company_id_account_id
    credit_notes ||--o{ credit_note_items : company_id_credit_note_id
    companies ||--o{ credit_note_items : company_id
    invoice_items ||--o{ credit_note_items : company_id_invoice_item_id
    credit_notes {
        int8 id
        int8 company_id
        timestamp created_at
        timestamp updated_at
        int8 invoice_id
        varchar credit_number
        date date
        numeric amount
        text reason
        varchar status
        int8 journal_entry_id
    }
    companies ||--o{ credit_notes : company_id
    invoices ||--o{ credit_notes : company_id_invoice_id
    journal_entries ||--o{ credit_notes : company_id_journal_entry_id
    currencies {
        int8 id
        varchar code
        varchar name
        varchar symbol
        int2 decimal_places
        bool is_active
        timestamp created_at
        timestamp updated_at
    }
    customer_refunds {
        int8 id
        int8 company_id
        timestamp created_at
        timestamp updated_at
        int8 credit_note_id
        int8 bank_account_id
        int8 journal_entry_id
        numeric amount
        date date
        varchar idempotency_key
        varchar request_hash
    }
    bank_accounts ||--o{ customer_refunds : company_id_bank_account_id
    credit_notes ||--o{ customer_refunds : company_id_credit_note_id
    companies ||--o{ customer_refunds : company_id
    journal_entries ||--o{ customer_refunds : company_id_journal_entry_id
    customers {
        int8 id
        int8 company_id
        timestamp created_at
        timestamp updated_at
        varchar name
        varchar email
        varchar phone
        text address
        varchar tax_number
        varchar status
    }
    companies ||--o{ customers : company_id
    document_sequences {
        int8 id
        int8 company_id
        timestamp created_at
        timestamp updated_at
        varchar type
        int8 next_number
    }
    companies ||--o{ document_sequences : company_id
    exchange_rates {
        int8 id
        varchar base_currency
        varchar quote_currency
        date date
        numeric rate
        timestamp created_at
        timestamp updated_at
    }
    expense_categories {
        int8 id
        int8 company_id
        timestamp created_at
        timestamp updated_at
        varchar name
        int8 parent_id
        int8 account_id
    }
    accounts ||--o{ expense_categories : company_id_account_id
    companies ||--o{ expense_categories : company_id
    expense_categories ||--o{ expense_categories : company_id_parent_id
    expenses {
        int8 id
        int8 company_id
        timestamp created_at
        timestamp updated_at
        int8 category_id
        int8 bank_account_id
        int8 journal_entry_id
        int8 tax_rate_id
        date date
        text description
        numeric amount
        numeric tax_amount
        varchar payment_method
        varchar status
        varchar idempotency_key
        varchar request_hash
    }
    bank_accounts ||--o{ expenses : company_id_bank_account_id
    expense_categories ||--o{ expenses : company_id_category_id
    companies ||--o{ expenses : company_id
    journal_entries ||--o{ expenses : company_id_journal_entry_id
    tax_rates ||--o{ expenses : company_id_tax_rate_id
    financial_years {
        int8 id
        int8 company_id
        timestamp created_at
        timestamp updated_at
        varchar name
        date start_date
        date end_date
        bool is_closed
    }
    companies ||--o{ financial_years : company_id
    invoice_items {
        int8 id
        int8 company_id
        timestamp created_at
        timestamp updated_at
        int8 invoice_id
        int8 account_id
        int8 tax_rate_id
        text description
        numeric quantity
        numeric unit_price
        numeric discount
        numeric tax_rate
        numeric subtotal
        numeric tax_amount
        numeric total
    }
    accounts ||--o{ invoice_items : company_id_account_id
    companies ||--o{ invoice_items : company_id
    invoices ||--o{ invoice_items : company_id_invoice_id
    tax_rates ||--o{ invoice_items : company_id_tax_rate_id
    invoices {
        int8 id
        int8 company_id
        timestamp created_at
        timestamp updated_at
        int8 customer_id
        varchar invoice_number
        date issue_date
        date due_date
        varchar currency
        varchar status
        numeric subtotal
        numeric tax_total
        numeric total
        numeric paid_total
        numeric credited_total
        text notes
        int8 journal_entry_id
    }
    customers ||--o{ invoices : company_id_customer_id
    companies ||--o{ invoices : company_id
    journal_entries ||--o{ invoices : company_id_journal_entry_id
    journal_entries {
        int8 id
        int8 company_id
        timestamp created_at
        timestamp updated_at
        date date
        varchar reference
        text description
        varchar source_type
        int8 source_id
        varchar status
        int8 created_by
        timestamp posted_at
        int8 reversal_of_id
    }
    companies ||--o{ journal_entries : company_id
    journal_entries ||--o{ journal_entries : company_id_reversal_of_id
    users ||--o{ journal_entries : created_by
    journal_lines {
        int8 id
        int8 company_id
        timestamp created_at
        timestamp updated_at
        int8 journal_entry_id
        int8 account_id
        numeric debit
        numeric credit
        text description
    }
    accounts ||--o{ journal_lines : company_id_account_id
    companies ||--o{ journal_lines : company_id
    journal_entries ||--o{ journal_lines : company_id_journal_entry_id
    payment_methods {
        int8 id
        int8 company_id
        timestamp created_at
        timestamp updated_at
        varchar name
        varchar type
        json details
        bool is_active
    }
    companies ||--o{ payment_methods : company_id
    payments {
        int8 id
        int8 company_id
        timestamp created_at
        timestamp updated_at
        int8 invoice_id
        int8 bank_account_id
        int8 journal_entry_id
        varchar payment_method
        numeric amount
        date paid_at
        varchar reference
        text notes
        varchar idempotency_key
        varchar request_hash
    }
    bank_accounts ||--o{ payments : company_id_bank_account_id
    companies ||--o{ payments : company_id
    invoices ||--o{ payments : company_id_invoice_id
    journal_entries ||--o{ payments : company_id_journal_entry_id
    permissions {
        int8 id
        varchar name
    }
    products {
        int8 id
        int8 company_id
        timestamp created_at
        timestamp updated_at
        varchar code
        varchar name
        text description
        numeric unit_price
        int8 account_id
        bool is_active
    }
    accounts ||--o{ products : company_id_account_id
    companies ||--o{ products : company_id
    reconciliation_sessions {
        int8 id
        int8 company_id
        timestamp created_at
        timestamp updated_at
        int8 bank_account_id
        date statement_date
        numeric closing_balance
        varchar status
        timestamp closed_at
    }
    bank_accounts ||--o{ reconciliation_sessions : company_id_bank_account_id
    companies ||--o{ reconciliation_sessions : company_id
    reconciliations {
        int8 id
        int8 company_id
        timestamp created_at
        timestamp updated_at
        int8 bank_transaction_id
        int8 journal_entry_id
        int8 journal_line_id
        int8 session_id
        numeric matched_amount
        varchar status
        timestamp reconciled_at
    }
    bank_transactions ||--o{ reconciliations : company_id_bank_transaction_id
    companies ||--o{ reconciliations : company_id
    journal_entries ||--o{ reconciliations : company_id_journal_entry_id
    journal_lines ||--o{ reconciliations : company_id_journal_line_id
    reconciliation_sessions ||--o{ reconciliations : company_id_session_id
    report_exports {
        int8 id
        int8 company_id
        int8 user_id
        varchar report
        varchar format
        json filters
        varchar status
        varchar disk
        varchar path
        text error
        timestamp created_at
        timestamp updated_at
    }
    companies ||--o{ report_exports : company_id
    users ||--o{ report_exports : user_id
    role_permissions {
        int8 role_id
        int8 permission_id
    }
    permissions ||--o{ role_permissions : permission_id
    roles ||--o{ role_permissions : role_id
    roles {
        int8 id
        varchar name
        varchar guard_name
        timestamp created_at
        timestamp updated_at
    }
    subscription_invoices {
        int8 id
        int8 company_id
        timestamp created_at
        timestamp updated_at
        int8 subscription_id
        varchar number
        numeric amount
        varchar currency
        date due_date
        varchar status
        date period_start
        date period_end
    }
    companies ||--o{ subscription_invoices : company_id
    subscriptions ||--o{ subscription_invoices : company_id_subscription_id
    subscription_payments {
        int8 id
        int8 company_id
        timestamp created_at
        timestamp updated_at
        int8 subscription_invoice_id
        numeric amount
        varchar provider_reference
        timestamp paid_at
    }
    companies ||--o{ subscription_payments : company_id
    subscription_invoices ||--o{ subscription_payments : company_id_subscription_invoice_id
    subscription_plans {
        int8 id
        varchar name
        text description
        numeric price
        varchar currency
        varchar interval
        json features
        bool is_active
        timestamp created_at
        timestamp updated_at
    }
    subscriptions {
        int8 id
        int8 company_id
        timestamp created_at
        timestamp updated_at
        int8 plan_id
        varchar status
        date start_date
        date end_date
        timestamp trial_ends_at
        timestamp cancelled_at
        numeric amount
        varchar currency
    }
    companies ||--o{ subscriptions : company_id
    subscription_plans ||--o{ subscriptions : plan_id
    suppliers {
        int8 id
        int8 company_id
        timestamp created_at
        timestamp updated_at
        varchar name
        varchar email
        varchar phone
        text address
        varchar tax_number
        varchar status
    }
    companies ||--o{ suppliers : company_id
    tax_rates {
        int8 id
        int8 company_id
        timestamp created_at
        timestamp updated_at
        varchar name
        numeric rate
        varchar type
        bool is_default
        bool is_active
    }
    companies ||--o{ tax_rates : company_id
    users {
        int8 id
        varchar name
        varchar email
        timestamp email_verified_at
        varchar password
        varchar remember_token
        timestamp created_at
        timestamp updated_at
        bool is_platform_admin
    }
```

## accounts

| Column | Database type | Nullable |
|---|---|---|
| id | bigint | No |
| company_id | bigint | No |
| created_at | timestamp(0) without time zone | Yes |
| updated_at | timestamp(0) without time zone | Yes |
| code | character varying(50) | No |
| name | character varying(255) | No |
| type | character varying(255) | No |
| parent_id | bigint | Yes |
| is_active | boolean | No |
| system_key | character varying(255) | Yes |
| cash_flow_class | character varying(255) | No |

## attachments

| Column | Database type | Nullable |
|---|---|---|
| id | bigint | No |
| company_id | bigint | No |
| created_at | timestamp(0) without time zone | Yes |
| updated_at | timestamp(0) without time zone | Yes |
| model_type | character varying(255) | No |
| model_id | bigint | No |
| disk | character varying(255) | No |
| file_path | character varying(255) | No |
| file_name | character varying(255) | No |
| mime_type | character varying(255) | No |
| size | bigint | No |

## audit_logs

| Column | Database type | Nullable |
|---|---|---|
| id | bigint | No |
| company_id | bigint | No |
| user_id | bigint | Yes |
| event | character varying(255) | No |
| model_type | character varying(255) | No |
| model_id | bigint | No |
| old_values | json | Yes |
| new_values | json | Yes |
| created_at | timestamp(0) without time zone | No |

## bank_accounts

| Column | Database type | Nullable |
|---|---|---|
| id | bigint | No |
| company_id | bigint | No |
| created_at | timestamp(0) without time zone | Yes |
| updated_at | timestamp(0) without time zone | Yes |
| name | character varying(255) | No |
| bank_name | character varying(255) | Yes |
| account_number | character varying(100) | Yes |
| currency | character varying(3) | No |
| account_id | bigint | No |
| linked_status | character varying(255) | No |

## bank_transactions

| Column | Database type | Nullable |
|---|---|---|
| id | bigint | No |
| company_id | bigint | No |
| created_at | timestamp(0) without time zone | Yes |
| updated_at | timestamp(0) without time zone | Yes |
| bank_account_id | bigint | No |
| date | date | No |
| description | text | No |
| amount | numeric(20,2) | No |
| transaction_type | character varying(255) | No |
| reference | character varying(255) | Yes |
| import_hash | character varying(64) | No |
| imported_at | timestamp(0) without time zone | No |

## bill_items

| Column | Database type | Nullable |
|---|---|---|
| id | bigint | No |
| company_id | bigint | No |
| created_at | timestamp(0) without time zone | Yes |
| updated_at | timestamp(0) without time zone | Yes |
| bill_id | bigint | No |
| account_id | bigint | No |
| tax_rate_id | bigint | Yes |
| description | text | No |
| quantity | numeric(16,4) | No |
| unit_price | numeric(20,4) | No |
| discount | numeric(20,2) | No |
| tax_rate | numeric(7,4) | No |
| subtotal | numeric(20,2) | No |
| tax_amount | numeric(20,2) | No |
| total | numeric(20,2) | No |

## bill_payments

| Column | Database type | Nullable |
|---|---|---|
| id | bigint | No |
| company_id | bigint | No |
| created_at | timestamp(0) without time zone | Yes |
| updated_at | timestamp(0) without time zone | Yes |
| bill_id | bigint | No |
| bank_account_id | bigint | No |
| journal_entry_id | bigint | No |
| payment_method | character varying(255) | No |
| amount | numeric(20,2) | No |
| paid_at | date | No |
| reference | character varying(255) | Yes |
| notes | text | Yes |
| idempotency_key | character varying(100) | No |
| request_hash | character varying(64) | No |

## bills

| Column | Database type | Nullable |
|---|---|---|
| id | bigint | No |
| company_id | bigint | No |
| created_at | timestamp(0) without time zone | Yes |
| updated_at | timestamp(0) without time zone | Yes |
| supplier_id | bigint | No |
| bill_number | character varying(50) | No |
| bill_date | date | No |
| due_date | date | No |
| currency | character varying(3) | No |
| status | character varying(255) | No |
| subtotal | numeric(20,2) | No |
| tax_total | numeric(20,2) | No |
| total | numeric(20,2) | No |
| paid_total | numeric(20,2) | No |
| credited_total | numeric(20,2) | No |
| notes | text | Yes |
| journal_entry_id | bigint | Yes |

## companies

| Column | Database type | Nullable |
|---|---|---|
| id | bigint | No |
| name | character varying(255) | No |
| legal_name | character varying(255) | Yes |
| email | character varying(255) | Yes |
| phone | character varying(50) | Yes |
| currency | character varying(3) | No |
| timezone | character varying(255) | No |
| financial_year_start_date | date | No |
| locked_through | date | Yes |
| status | character varying(255) | No |
| created_at | timestamp(0) without time zone | Yes |
| updated_at | timestamp(0) without time zone | Yes |

## company_invitations

| Column | Database type | Nullable |
|---|---|---|
| id | bigint | No |
| company_id | bigint | No |
| created_at | timestamp(0) without time zone | Yes |
| updated_at | timestamp(0) without time zone | Yes |
| email | character varying(255) | No |
| token_hash | character varying(64) | No |
| role_id | bigint | No |
| expires_at | timestamp(0) without time zone | No |
| accepted_at | timestamp(0) without time zone | Yes |

## company_permission_overrides

| Column | Database type | Nullable |
|---|---|---|
| id | bigint | No |
| company_id | bigint | No |
| role_id | bigint | No |
| permission_id | bigint | No |
| allowed | boolean | No |
| created_at | timestamp(0) without time zone | Yes |
| updated_at | timestamp(0) without time zone | Yes |

## company_settings

| Column | Database type | Nullable |
|---|---|---|
| id | bigint | No |
| company_id | bigint | No |
| created_at | timestamp(0) without time zone | Yes |
| updated_at | timestamp(0) without time zone | Yes |
| setting_key | character varying(100) | No |
| setting_value | text | Yes |

## company_users

| Column | Database type | Nullable |
|---|---|---|
| id | bigint | No |
| company_id | bigint | No |
| created_at | timestamp(0) without time zone | Yes |
| updated_at | timestamp(0) without time zone | Yes |
| user_id | bigint | No |
| role_id | bigint | No |
| is_active | boolean | No |

## credit_note_items

| Column | Database type | Nullable |
|---|---|---|
| id | bigint | No |
| company_id | bigint | No |
| created_at | timestamp(0) without time zone | Yes |
| updated_at | timestamp(0) without time zone | Yes |
| credit_note_id | bigint | No |
| account_id | bigint | No |
| description | text | No |
| amount | numeric(20,2) | No |
| tax_amount | numeric(20,2) | No |
| invoice_item_id | bigint | No |

## credit_notes

| Column | Database type | Nullable |
|---|---|---|
| id | bigint | No |
| company_id | bigint | No |
| created_at | timestamp(0) without time zone | Yes |
| updated_at | timestamp(0) without time zone | Yes |
| invoice_id | bigint | No |
| credit_number | character varying(50) | No |
| date | date | No |
| amount | numeric(20,2) | No |
| reason | text | No |
| status | character varying(255) | No |
| journal_entry_id | bigint | Yes |

## currencies

| Column | Database type | Nullable |
|---|---|---|
| id | bigint | No |
| code | character varying(3) | No |
| name | character varying(255) | No |
| symbol | character varying(8) | No |
| decimal_places | smallint | No |
| is_active | boolean | No |
| created_at | timestamp(0) without time zone | Yes |
| updated_at | timestamp(0) without time zone | Yes |

## customer_refunds

| Column | Database type | Nullable |
|---|---|---|
| id | bigint | No |
| company_id | bigint | No |
| created_at | timestamp(0) without time zone | Yes |
| updated_at | timestamp(0) without time zone | Yes |
| credit_note_id | bigint | No |
| bank_account_id | bigint | No |
| journal_entry_id | bigint | No |
| amount | numeric(20,2) | No |
| date | date | No |
| idempotency_key | character varying(255) | No |
| request_hash | character varying(64) | No |

## customers

| Column | Database type | Nullable |
|---|---|---|
| id | bigint | No |
| company_id | bigint | No |
| created_at | timestamp(0) without time zone | Yes |
| updated_at | timestamp(0) without time zone | Yes |
| name | character varying(255) | No |
| email | character varying(255) | Yes |
| phone | character varying(50) | Yes |
| address | text | Yes |
| tax_number | character varying(50) | Yes |
| status | character varying(255) | No |

## document_sequences

| Column | Database type | Nullable |
|---|---|---|
| id | bigint | No |
| company_id | bigint | No |
| created_at | timestamp(0) without time zone | Yes |
| updated_at | timestamp(0) without time zone | Yes |
| type | character varying(255) | No |
| next_number | bigint | No |

## exchange_rates

| Column | Database type | Nullable |
|---|---|---|
| id | bigint | No |
| base_currency | character varying(3) | No |
| quote_currency | character varying(3) | No |
| date | date | No |
| rate | numeric(24,10) | No |
| created_at | timestamp(0) without time zone | Yes |
| updated_at | timestamp(0) without time zone | Yes |

## expense_categories

| Column | Database type | Nullable |
|---|---|---|
| id | bigint | No |
| company_id | bigint | No |
| created_at | timestamp(0) without time zone | Yes |
| updated_at | timestamp(0) without time zone | Yes |
| name | character varying(255) | No |
| parent_id | bigint | Yes |
| account_id | bigint | No |

## expenses

| Column | Database type | Nullable |
|---|---|---|
| id | bigint | No |
| company_id | bigint | No |
| created_at | timestamp(0) without time zone | Yes |
| updated_at | timestamp(0) without time zone | Yes |
| category_id | bigint | No |
| bank_account_id | bigint | No |
| journal_entry_id | bigint | Yes |
| tax_rate_id | bigint | Yes |
| date | date | No |
| description | text | No |
| amount | numeric(20,2) | No |
| tax_amount | numeric(20,2) | No |
| payment_method | character varying(255) | No |
| status | character varying(255) | No |
| idempotency_key | character varying(100) | Yes |
| request_hash | character varying(64) | Yes |

## financial_years

| Column | Database type | Nullable |
|---|---|---|
| id | bigint | No |
| company_id | bigint | No |
| created_at | timestamp(0) without time zone | Yes |
| updated_at | timestamp(0) without time zone | Yes |
| name | character varying(255) | No |
| start_date | date | No |
| end_date | date | No |
| is_closed | boolean | No |

## invoice_items

| Column | Database type | Nullable |
|---|---|---|
| id | bigint | No |
| company_id | bigint | No |
| created_at | timestamp(0) without time zone | Yes |
| updated_at | timestamp(0) without time zone | Yes |
| invoice_id | bigint | No |
| account_id | bigint | No |
| tax_rate_id | bigint | Yes |
| description | text | No |
| quantity | numeric(16,4) | No |
| unit_price | numeric(20,4) | No |
| discount | numeric(20,2) | No |
| tax_rate | numeric(7,4) | No |
| subtotal | numeric(20,2) | No |
| tax_amount | numeric(20,2) | No |
| total | numeric(20,2) | No |

## invoices

| Column | Database type | Nullable |
|---|---|---|
| id | bigint | No |
| company_id | bigint | No |
| created_at | timestamp(0) without time zone | Yes |
| updated_at | timestamp(0) without time zone | Yes |
| customer_id | bigint | No |
| invoice_number | character varying(50) | No |
| issue_date | date | No |
| due_date | date | No |
| currency | character varying(3) | No |
| status | character varying(255) | No |
| subtotal | numeric(20,2) | No |
| tax_total | numeric(20,2) | No |
| total | numeric(20,2) | No |
| paid_total | numeric(20,2) | No |
| credited_total | numeric(20,2) | No |
| notes | text | Yes |
| journal_entry_id | bigint | Yes |

## journal_entries

| Column | Database type | Nullable |
|---|---|---|
| id | bigint | No |
| company_id | bigint | No |
| created_at | timestamp(0) without time zone | Yes |
| updated_at | timestamp(0) without time zone | Yes |
| date | date | No |
| reference | character varying(100) | No |
| description | text | Yes |
| source_type | character varying(255) | Yes |
| source_id | bigint | Yes |
| status | character varying(255) | No |
| created_by | bigint | No |
| posted_at | timestamp(0) without time zone | Yes |
| reversal_of_id | bigint | Yes |

## journal_lines

| Column | Database type | Nullable |
|---|---|---|
| id | bigint | No |
| company_id | bigint | No |
| created_at | timestamp(0) without time zone | Yes |
| updated_at | timestamp(0) without time zone | Yes |
| journal_entry_id | bigint | No |
| account_id | bigint | No |
| debit | numeric(20,2) | No |
| credit | numeric(20,2) | No |
| description | text | Yes |

## payment_methods

| Column | Database type | Nullable |
|---|---|---|
| id | bigint | No |
| company_id | bigint | No |
| created_at | timestamp(0) without time zone | Yes |
| updated_at | timestamp(0) without time zone | Yes |
| name | character varying(255) | No |
| type | character varying(255) | No |
| details | json | Yes |
| is_active | boolean | No |

## payments

| Column | Database type | Nullable |
|---|---|---|
| id | bigint | No |
| company_id | bigint | No |
| created_at | timestamp(0) without time zone | Yes |
| updated_at | timestamp(0) without time zone | Yes |
| invoice_id | bigint | No |
| bank_account_id | bigint | No |
| journal_entry_id | bigint | No |
| payment_method | character varying(255) | No |
| amount | numeric(20,2) | No |
| paid_at | date | No |
| reference | character varying(255) | Yes |
| notes | text | Yes |
| idempotency_key | character varying(100) | No |
| request_hash | character varying(64) | No |

## permissions

| Column | Database type | Nullable |
|---|---|---|
| id | bigint | No |
| name | character varying(255) | No |

## products

| Column | Database type | Nullable |
|---|---|---|
| id | bigint | No |
| company_id | bigint | No |
| created_at | timestamp(0) without time zone | Yes |
| updated_at | timestamp(0) without time zone | Yes |
| code | character varying(255) | No |
| name | character varying(255) | No |
| description | text | Yes |
| unit_price | numeric(20,4) | No |
| account_id | bigint | No |
| is_active | boolean | No |

## reconciliation_sessions

| Column | Database type | Nullable |
|---|---|---|
| id | bigint | No |
| company_id | bigint | No |
| created_at | timestamp(0) without time zone | Yes |
| updated_at | timestamp(0) without time zone | Yes |
| bank_account_id | bigint | No |
| statement_date | date | No |
| closing_balance | numeric(20,2) | No |
| status | character varying(255) | No |
| closed_at | timestamp(0) without time zone | Yes |

## reconciliations

| Column | Database type | Nullable |
|---|---|---|
| id | bigint | No |
| company_id | bigint | No |
| created_at | timestamp(0) without time zone | Yes |
| updated_at | timestamp(0) without time zone | Yes |
| bank_transaction_id | bigint | No |
| journal_entry_id | bigint | No |
| journal_line_id | bigint | No |
| session_id | bigint | Yes |
| matched_amount | numeric(20,2) | No |
| status | character varying(255) | No |
| reconciled_at | timestamp(0) without time zone | No |

## report_exports

| Column | Database type | Nullable |
|---|---|---|
| id | bigint | No |
| company_id | bigint | No |
| user_id | bigint | No |
| report | character varying(255) | No |
| format | character varying(8) | No |
| filters | json | No |
| status | character varying(255) | No |
| disk | character varying(255) | No |
| path | character varying(255) | Yes |
| error | text | Yes |
| created_at | timestamp(0) without time zone | Yes |
| updated_at | timestamp(0) without time zone | Yes |

## role_permissions

| Column | Database type | Nullable |
|---|---|---|
| role_id | bigint | No |
| permission_id | bigint | No |

## roles

| Column | Database type | Nullable |
|---|---|---|
| id | bigint | No |
| name | character varying(255) | No |
| guard_name | character varying(255) | No |
| created_at | timestamp(0) without time zone | Yes |
| updated_at | timestamp(0) without time zone | Yes |

## subscription_invoices

| Column | Database type | Nullable |
|---|---|---|
| id | bigint | No |
| company_id | bigint | No |
| created_at | timestamp(0) without time zone | Yes |
| updated_at | timestamp(0) without time zone | Yes |
| subscription_id | bigint | No |
| number | character varying(255) | No |
| amount | numeric(20,2) | No |
| currency | character varying(3) | No |
| due_date | date | No |
| status | character varying(255) | No |
| period_start | date | No |
| period_end | date | No |

## subscription_payments

| Column | Database type | Nullable |
|---|---|---|
| id | bigint | No |
| company_id | bigint | No |
| created_at | timestamp(0) without time zone | Yes |
| updated_at | timestamp(0) without time zone | Yes |
| subscription_invoice_id | bigint | No |
| amount | numeric(20,2) | No |
| provider_reference | character varying(255) | No |
| paid_at | timestamp(0) without time zone | No |

## subscription_plans

| Column | Database type | Nullable |
|---|---|---|
| id | bigint | No |
| name | character varying(255) | No |
| description | text | Yes |
| price | numeric(20,2) | No |
| currency | character varying(3) | No |
| interval | character varying(255) | No |
| features | json | No |
| is_active | boolean | No |
| created_at | timestamp(0) without time zone | Yes |
| updated_at | timestamp(0) without time zone | Yes |

## subscriptions

| Column | Database type | Nullable |
|---|---|---|
| id | bigint | No |
| company_id | bigint | No |
| created_at | timestamp(0) without time zone | Yes |
| updated_at | timestamp(0) without time zone | Yes |
| plan_id | bigint | No |
| status | character varying(255) | No |
| start_date | date | No |
| end_date | date | Yes |
| trial_ends_at | timestamp(0) without time zone | Yes |
| cancelled_at | timestamp(0) without time zone | Yes |
| amount | numeric(20,2) | No |
| currency | character varying(3) | No |

## suppliers

| Column | Database type | Nullable |
|---|---|---|
| id | bigint | No |
| company_id | bigint | No |
| created_at | timestamp(0) without time zone | Yes |
| updated_at | timestamp(0) without time zone | Yes |
| name | character varying(255) | No |
| email | character varying(255) | Yes |
| phone | character varying(50) | Yes |
| address | text | Yes |
| tax_number | character varying(50) | Yes |
| status | character varying(255) | No |

## tax_rates

| Column | Database type | Nullable |
|---|---|---|
| id | bigint | No |
| company_id | bigint | No |
| created_at | timestamp(0) without time zone | Yes |
| updated_at | timestamp(0) without time zone | Yes |
| name | character varying(255) | No |
| rate | numeric(7,4) | No |
| type | character varying(255) | No |
| is_default | boolean | No |
| is_active | boolean | No |

## users

| Column | Database type | Nullable |
|---|---|---|
| id | bigint | No |
| name | character varying(255) | No |
| email | character varying(255) | No |
| email_verified_at | timestamp(0) without time zone | Yes |
| password | character varying(255) | No |
| remember_token | character varying(100) | Yes |
| created_at | timestamp(0) without time zone | Yes |
| updated_at | timestamp(0) without time zone | Yes |
| is_platform_admin | boolean | No |
