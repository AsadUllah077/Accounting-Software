<?php

// Builds a documentation PDF using schema metadata only; never reads business rows or credentials.
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$purposes = [
    'companies' => 'Company profile, base currency, timezone, status and accounting lock date.',
    'company_settings' => 'Additional company key/value settings; the current profile form writes companies instead.',
    'users' => 'Global login identity, password hash, email verification and platform-administrator flag.',
    'company_users' => 'Membership linking a user to a company, with its role and active-access flag.',
    'roles' => 'Shared predefined company role names, such as Owner and Accountant.',
    'permissions' => 'Shared permission names checked by protected backend operations.',
    'role_permissions' => 'Default permission assignments for each predefined role.',
    'company_permission_overrides' => 'Per-company allowed/denied overrides for role permissions.',
    'company_invitations' => 'Recipient, role, hashed invitation token, expiry and acceptance timestamp.',
    'customers' => 'Company customer contact information and active/archived status.',
    'suppliers' => 'Company supplier contact information and active/archived status.',
    'invoices' => 'Sales document header, customer, dates, totals, status and posted-journal link.',
    'invoice_items' => 'Invoice lines, account, quantity, unit price, discount and tax snapshot.',
    'payments' => 'Customer receipts allocated to an invoice, linked to a bank and journal.',
    'credit_notes' => 'Posted customer credit header linked to the original invoice.',
    'credit_note_items' => 'Credited original invoice lines, before-tax amounts and tax adjustments.',
    'customer_refunds' => 'Cash paid back against a credit note, with bank and journal links.',
    'bills' => 'Supplier purchase document header, totals, payment summary and posted-journal link.',
    'bill_items' => 'Purchase lines linked to expense/asset accounts, with quantity, discount and tax.',
    'bill_payments' => 'Supplier payments allocated to one bill, linked to a bank and journal.',
    'expenses' => 'Direct paid expenses, category, bank, tax, posting status and retry protection.',
    'expense_categories' => 'Expense category tree and the expense account assigned to each category.',
    'accounts' => 'Chart of accounts: code, account type, parent, system purpose and cash-flow class.',
    'journal_entries' => 'Accounting entry header: date, reference, source, author, posting and reversal link.',
    'journal_lines' => 'The debit/credit amounts posted to individual ledger accounts.',
    'bank_accounts' => 'Company bank metadata and its associated asset ledger account.',
    'bank_transactions' => 'Imported signed bank-statement rows and duplicate-detection fingerprints.',
    'reconciliations' => 'One-to-one matching of a bank-statement transaction to a posted bank journal line.',
    'reconciliation_sessions' => 'Statement end date, closing balance and open/closed reconciliation certificate.',
    'tax_rates' => 'Company tax definitions used to snapshot tax on document lines and expenses.',
    'currencies' => 'Shared supported currency codes, names, symbols and decimal-place reference data.',
    'attachments' => 'Private file metadata and the invoice/bill/expense record to which the file belongs.',
    'audit_logs' => 'Append-only actor/company/event history with affected record and old/new values.',
    'financial_years' => 'Company accounting-year dates and closed-period status.',
    'document_sequences' => 'Next per-company invoice, bill and credit-note number.',
    'products' => 'Product/service reference schema; a catalog workflow is not currently connected.',
    'exchange_rates' => 'Shared dated currency-rate reference schema; foreign-currency posting is not enabled.',
    'payment_methods' => 'Company payment-method reference schema; current workflows use bank payments.',
    'subscription_plans' => 'Platform plan prices, interval, currency, features and active availability.',
    'subscriptions' => 'Company subscription history, selected plan, trial, cancellation and billing-period dates.',
    'subscription_invoices' => 'Platform subscription charges for unique billing periods, separate from sales invoices.',
    'subscription_payments' => 'Platform-admin-verified settlement of subscription invoices.',
    'report_exports' => 'Owner, company, filters, format, job status and private generated-report path.',
    'password_reset_tokens' => 'Hashed password-recovery tokens keyed by login email.',
    'sessions' => 'Database-backed authentication/session payloads, including active-company selection.',
    'cache' => 'Database-backed cache values used by the current local configuration.',
    'cache_locks' => 'Database-backed distributed locks, including invoice-email delivery locks.',
    'jobs' => 'Pending database queue payloads for invoice emails and background exports.',
    'job_batches' => 'Laravel batch-job bookkeeping; no dedicated batch workflow is currently exposed.',
    'failed_jobs' => 'Failed queued-job metadata used for operational troubleshooting.',
    'migrations' => 'Which schema migrations have run and their batch numbers.',
    'personal_access_tokens' => 'Sanctum token storage if installed; a public token-management API is not exposed.',
];

$escape = fn ($value) => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$tables = array_column(Illuminate\Support\Facades\Schema::getTables(), 'name');
sort($tables);
$appendix = '<section class="chapter"><div class="eyebrow">APPENDIX A</div><h1>Complete database reference</h1><p>Generated from the migrated local database. These are schema definitions, not customer records. Each table below explains its purpose, columns, nullable fields, keys and relationships.</p><p>The local database uses SQLite. The migrations define the PostgreSQL deployment schema; SQLite type affinity and database safeguards differ. Financial precision and quantities are described in the main guide.</p><div class="note">'.count($tables).' tables are present in this schema snapshot. All are covered in this appendix.</div></section>';
foreach ($tables as $table) {
    if (! isset($purposes[$table])) {
        throw new RuntimeException('Add a purpose description for table: '.$table);
    }
    $columns = Illuminate\Support\Facades\Schema::getColumns($table);
    $indexes = Illuminate\Support\Facades\Schema::getIndexes($table);
    $fks = Illuminate\Support\Facades\Schema::getForeignKeys($table);
    $appendix .= '<div class="schema-block"><h2>'.$escape($table).'</h2><p>'.$escape($purposes[$table]).'</p><table class="schema"><thead><tr><th>Column</th><th>Database type</th><th>Nullable</th></tr></thead><tbody>';
    foreach ($columns as $column) {
        $appendix .= '<tr><td><code>'.$escape($column['name']).'</code></td><td>'.$escape($column['type']).'</td><td>'.($column['nullable'] ? 'Yes' : 'No').'</td></tr>';
    }
    $appendix .= '</tbody></table>';
    foreach ($indexes as $index) {
        if ($index['primary'] || $index['unique']) {
            $appendix .= '<p class="key">'.($index['primary'] ? 'Primary key: ' : 'Unique: ').$escape(implode(' + ', $index['columns'])).'.</p>';
        }
    }
    foreach ($fks as $fk) {
        $appendix .= '<p class="key">FK: '.$escape(implode(' + ', $fk['columns'])).' → '.$escape($fk['foreign_table']).' ('.$escape(implode(' + ', $fk['foreign_columns'])).').</p>';
    }
    $appendix .= '</div>';
}

$source = file_get_contents(__DIR__.'/../docs/project-guide-source.html');
$html = str_replace('<!-- SCHEMA_APPENDIX -->', $appendix, $source);
$html = str_replace('<!-- TABLE_COUNT -->', (string) count($tables), $html);
file_put_contents(__DIR__.'/../docs/project-guide.html', $html);
$options = new Dompdf\Options;
$options->set('isRemoteEnabled', false);
$options->set('isPhpEnabled', false);
$options->set('defaultFont', 'DejaVu Sans');
$pdf = new Dompdf\Dompdf($options);
$pdf->setPaper('A4');
$pdf->loadHtml($html, 'UTF-8');
$pdf->render();
$font = $pdf->getFontMetrics()->getFont('DejaVu Sans');
$pdf->getCanvas()->page_text(42, 808, 'Ledger | Project & Database Guide', $font, 8, [0.32, 0.39, 0.46]);
$pdf->getCanvas()->page_text(446, 808, 'Page {PAGE_NUM} of {PAGE_COUNT}', $font, 8, [0.32, 0.39, 0.46]);
$pdf->addInfo('Title', 'Ledger — Complete Project and Database Workflow Guide');
$pdf->addInfo('Subject', 'Implemented application workflows, storage tables, accounting effects and schema reference');
$pdf->addInfo('Author', 'Ledger Project Documentation');
file_put_contents(__DIR__.'/../docs/Ledger-Project-Guide.pdf', $pdf->output());
echo 'Created docs/Ledger-Project-Guide.pdf; '.$pdf->getCanvas()->get_page_count().' pages; '.count($tables)." documented tables.\n";
