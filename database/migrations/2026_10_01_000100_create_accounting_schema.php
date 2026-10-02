<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function tenant(Blueprint $t): void
    {
        $t->id();
        $t->foreignId('company_id')->constrained()->restrictOnDelete();
        $t->unique(['company_id', 'id']);
        $t->timestamps();
    }

    private function link(Blueprint $t, string $column, string $table, bool $nullable = false): void
    {
        $t->unsignedBigInteger($column)->nullable($nullable);
        $t->foreign(['company_id', $column])->references(['company_id', 'id'])->on($table)->restrictOnDelete();
    }

    public function up(): void
    {
        Schema::create('currencies', function (Blueprint $t) {
            $t->id();
            $t->string('code', 3)->unique();
            $t->string('name');
            $t->string('symbol', 8);
            $t->unsignedTinyInteger('decimal_places')->default(2);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });
        Schema::create('companies', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('legal_name')->nullable();
            $t->string('email')->nullable();
            $t->string('phone', 50)->nullable();
            $t->string('currency', 3)->default('USD');
            $t->foreign('currency')->references('code')->on('currencies');
            $t->string('timezone')->default('UTC');
            $t->date('financial_year_start_date');
            $t->date('locked_through')->nullable();
            $t->string('status')->default('active');
            $t->timestamps();
        });
        Schema::create('roles', function (Blueprint $t) {
            $t->id();
            $t->string('name')->unique();
            $t->string('guard_name')->default('web');
            $t->timestamps();
        });
        Schema::create('permissions', function (Blueprint $t) {
            $t->id();
            $t->string('name')->unique();
        });
        Schema::create('role_permissions', function (Blueprint $t) {
            $t->foreignId('role_id')->constrained()->cascadeOnDelete();
            $t->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $t->primary(['role_id', 'permission_id']);
        });
        Schema::create('company_users', function (Blueprint $t) {
            $this->tenant($t);
            $t->foreignId('user_id')->constrained()->restrictOnDelete();
            $t->foreignId('role_id')->constrained()->restrictOnDelete();
            $t->boolean('is_active')->default(true);
            $t->unique(['company_id', 'user_id']);
        });
        Schema::create('company_settings', function (Blueprint $t) {
            $this->tenant($t);
            $t->string('setting_key', 100);
            $t->text('setting_value')->nullable();
            $t->unique(['company_id', 'setting_key']);
        });
        Schema::create('company_invitations', function (Blueprint $t) {
            $this->tenant($t);
            $t->string('email');
            $t->string('token_hash', 64)->unique();
            $t->foreignId('role_id')->constrained();
            $t->timestamp('expires_at');
            $t->timestamp('accepted_at')->nullable();
        });
        foreach (['customers', 'suppliers'] as $table) {
            Schema::create($table, function (Blueprint $t) {
                $this->tenant($t);
                $t->string('name');
                $t->string('email')->nullable();
                $t->string('phone', 50)->nullable();
                $t->text('address')->nullable();
                $t->string('tax_number', 50)->nullable();
                $t->string('status')->default('active');
                $t->index(['company_id', 'name']);
            });
        }
        Schema::create('accounts', function (Blueprint $t) {
            $this->tenant($t);
            $t->string('code', 50);
            $t->string('name');
            $t->string('type');
            $this->link($t, 'parent_id', 'accounts', true);
            $t->boolean('is_active')->default(true);
            $t->string('system_key')->nullable();
            $t->unique(['company_id', 'code']);
            $t->unique(['company_id', 'system_key']);
        });
        Schema::create('tax_rates', function (Blueprint $t) {
            $this->tenant($t);
            $t->string('name');
            $t->decimal('rate', 7, 4);
            $t->string('type')->default('sales');
            $t->boolean('is_default')->default(false);
            $t->boolean('is_active')->default(true);
        });
        Schema::create('financial_years', function (Blueprint $t) {
            $this->tenant($t);
            $t->string('name');
            $t->date('start_date');
            $t->date('end_date');
            $t->boolean('is_closed')->default(false);
            $t->unique(['company_id', 'start_date']);
        });
        Schema::create('document_sequences', function (Blueprint $t) {
            $this->tenant($t);
            $t->string('type');
            $t->unsignedBigInteger('next_number')->default(1);
            $t->unique(['company_id', 'type']);
        });
        Schema::create('journal_entries', function (Blueprint $t) {
            $this->tenant($t);
            $t->date('date');
            $t->string('reference', 100);
            $t->text('description')->nullable();
            $t->string('source_type')->nullable();
            $t->unsignedBigInteger('source_id')->nullable();
            $t->string('status')->default('draft');
            $t->foreignId('created_by')->constrained('users');
            $t->timestamp('posted_at')->nullable();
            $this->link($t, 'reversal_of_id', 'journal_entries', true);
            $t->unique(['company_id', 'reversal_of_id']);
            $t->unique(['company_id', 'source_type', 'source_id']);
            $t->index(['company_id', 'status', 'date']);
        });
        Schema::create('journal_lines', function (Blueprint $t) {
            $this->tenant($t);
            $this->link($t, 'journal_entry_id', 'journal_entries');
            $this->link($t, 'account_id', 'accounts');
            $t->decimal('debit', 20, 2)->default(0);
            $t->decimal('credit', 20, 2)->default(0);
            $t->text('description')->nullable();
            $t->index(['company_id', 'account_id', 'journal_entry_id']);
        });
        Schema::create('bank_accounts', function (Blueprint $t) {
            $this->tenant($t);
            $t->string('name');
            $t->string('bank_name')->nullable();
            $t->string('account_number', 100)->nullable();
            $t->string('currency', 3);
            $this->link($t, 'account_id', 'accounts');
            $t->string('linked_status')->default('manual');
            $t->unique(['company_id', 'account_id']);
        });
        Schema::create('payment_methods', function (Blueprint $t) {
            $this->tenant($t);
            $t->string('name');
            $t->string('type');
            $t->json('details')->nullable();
            $t->boolean('is_active')->default(true);
        });
        foreach (['invoices' => 'customers', 'bills' => 'suppliers'] as $table => $contact) {
            Schema::create($table, function (Blueprint $t) use ($table, $contact) {
                $this->tenant($t);
                $this->link($t, $table === 'invoices' ? 'customer_id' : 'supplier_id', $contact);
                $t->string($table === 'invoices' ? 'invoice_number' : 'bill_number', 50);
                $t->date($table === 'invoices' ? 'issue_date' : 'bill_date');
                $t->date('due_date');
                $t->string('currency', 3);
                $t->string('status')->default('draft');
                $t->decimal('subtotal', 20, 2)->default(0);
                $t->decimal('tax_total', 20, 2)->default(0);
                $t->decimal('total', 20, 2)->default(0);
                $t->decimal('paid_total', 20, 2)->default(0);
                $t->decimal('credited_total', 20, 2)->default(0);
                $t->text('notes')->nullable();
                $this->link($t, 'journal_entry_id', 'journal_entries', true);
                $t->unique(['company_id', $table === 'invoices' ? 'invoice_number' : 'bill_number']);
                $t->index(['company_id', 'status', 'due_date']);
            });
        }
        Schema::create('products', function (Blueprint $t) {
            $this->tenant($t);
            $t->string('code');
            $t->string('name');
            $t->text('description')->nullable();
            $t->decimal('unit_price', 20, 4);
            $this->link($t, 'account_id', 'accounts');
            $t->boolean('is_active')->default(true);
            $t->unique(['company_id', 'code']);
        });
        foreach (['invoice_items' => 'invoices', 'bill_items' => 'bills'] as $table => $parent) {
            Schema::create($table, function (Blueprint $t) use ($parent) {
                $this->tenant($t);
                $this->link($t, $parent === 'invoices' ? 'invoice_id' : 'bill_id', $parent);
                $this->link($t, 'account_id', 'accounts');
                $this->link($t, 'tax_rate_id', 'tax_rates', true);
                $t->text('description');
                $t->decimal('quantity', 16, 4);
                $t->decimal('unit_price', 20, 4);
                $t->decimal('discount', 20, 2)->default(0);
                $t->decimal('tax_rate', 7, 4)->default(0);
                $t->decimal('subtotal', 20, 2);
                $t->decimal('tax_amount', 20, 2);
                $t->decimal('total', 20, 2);
            });
        }
        foreach (['payments' => 'invoices', 'bill_payments' => 'bills'] as $table => $parent) {
            Schema::create($table, function (Blueprint $t) use ($parent) {
                $this->tenant($t);
                $this->link($t, $parent === 'invoices' ? 'invoice_id' : 'bill_id', $parent);
                $this->link($t, 'bank_account_id', 'bank_accounts');
                $this->link($t, 'journal_entry_id', 'journal_entries');
                $t->string('payment_method')->default('bank');
                $t->decimal('amount', 20, 2);
                $t->date('paid_at');
                $t->string('reference')->nullable();
                $t->text('notes')->nullable();
                $t->string('idempotency_key', 100);
                $t->string('request_hash', 64);
                $t->unique(['company_id', 'idempotency_key']);
            });
        }
        Schema::create('credit_notes', function (Blueprint $t) {
            $this->tenant($t);
            $this->link($t, 'invoice_id', 'invoices');
            $t->string('credit_number', 50);
            $t->date('date');
            $t->decimal('amount', 20, 2);
            $t->text('reason');
            $t->string('status')->default('draft');
            $this->link($t, 'journal_entry_id', 'journal_entries', true);
            $t->unique(['company_id', 'credit_number']);
        });
        Schema::create('credit_note_items', function (Blueprint $t) {
            $this->tenant($t);
            $this->link($t, 'credit_note_id', 'credit_notes');
            $this->link($t, 'account_id', 'accounts');
            $t->text('description');
            $t->decimal('amount', 20, 2);
            $t->decimal('tax_amount', 20, 2)->default(0);
        });
        Schema::create('customer_refunds', function (Blueprint $t) {
            $this->tenant($t);
            $this->link($t, 'credit_note_id', 'credit_notes');
            $this->link($t, 'bank_account_id', 'bank_accounts');
            $this->link($t, 'journal_entry_id', 'journal_entries');
            $t->decimal('amount', 20, 2);
            $t->date('date');
            $t->string('idempotency_key')->unique();
        });
        Schema::create('expense_categories', function (Blueprint $t) {
            $this->tenant($t);
            $t->string('name');
            $this->link($t, 'parent_id', 'expense_categories', true);
            $this->link($t, 'account_id', 'accounts');
        });
        Schema::create('expenses', function (Blueprint $t) {
            $this->tenant($t);
            $this->link($t, 'category_id', 'expense_categories');
            $this->link($t, 'bank_account_id', 'bank_accounts');
            $this->link($t, 'journal_entry_id', 'journal_entries', true);
            $this->link($t, 'tax_rate_id', 'tax_rates', true);
            $t->date('date');
            $t->text('description');
            $t->decimal('amount', 20, 2);
            $t->decimal('tax_amount', 20, 2)->default(0);
            $t->string('payment_method')->default('bank');
            $t->string('status')->default('draft');
        });
        Schema::create('reconciliation_sessions', function (Blueprint $t) {
            $this->tenant($t);
            $this->link($t, 'bank_account_id', 'bank_accounts');
            $t->date('statement_date');
            $t->decimal('closing_balance', 20, 2);
            $t->string('status')->default('open');
            $t->timestamp('closed_at')->nullable();
        });
        Schema::create('bank_transactions', function (Blueprint $t) {
            $this->tenant($t);
            $this->link($t, 'bank_account_id', 'bank_accounts');
            $t->date('date');
            $t->text('description');
            $t->decimal('amount', 20, 2);
            $t->string('transaction_type');
            $t->string('reference')->nullable();
            $t->string('import_hash', 64);
            $t->timestamp('imported_at');
            $t->unique(['company_id', 'bank_account_id', 'import_hash']);
        });
        Schema::create('reconciliations', function (Blueprint $t) {
            $this->tenant($t);
            $this->link($t, 'bank_transaction_id', 'bank_transactions');
            $this->link($t, 'journal_entry_id', 'journal_entries');
            $this->link($t, 'journal_line_id', 'journal_lines');
            $this->link($t, 'session_id', 'reconciliation_sessions', true);
            $t->decimal('matched_amount', 20, 2);
            $t->string('status')->default('matched');
            $t->timestamp('reconciled_at');
            $t->unique(['company_id', 'bank_transaction_id']);
            $t->unique(['company_id', 'journal_line_id']);
        });
        Schema::create('attachments', function (Blueprint $t) {
            $this->tenant($t);
            $t->string('model_type');
            $t->unsignedBigInteger('model_id');
            $t->string('disk')->default('s3');
            $t->string('file_path');
            $t->string('file_name');
            $t->string('mime_type');
            $t->unsignedBigInteger('size');
            $t->index(['company_id', 'model_type', 'model_id']);
        });
        Schema::create('audit_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained();
            $t->foreignId('user_id')->nullable()->constrained();
            $t->string('event');
            $t->string('model_type');
            $t->unsignedBigInteger('model_id');
            $t->json('old_values')->nullable();
            $t->json('new_values')->nullable();
            $t->timestamp('created_at');
            $t->index(['company_id', 'created_at']);
        });
        Schema::create('exchange_rates', function (Blueprint $t) {
            $t->id();
            $t->string('base_currency', 3);
            $t->string('quote_currency', 3);
            $t->date('date');
            $t->decimal('rate', 24, 10);
            $t->unique(['base_currency', 'quote_currency', 'date']);
            $t->timestamps();
        });
        Schema::create('subscription_plans', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->text('description')->nullable();
            $t->decimal('price', 20, 2);
            $t->string('currency', 3)->default('USD');
            $t->string('interval')->default('monthly');
            $t->json('features');
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });
        Schema::create('subscriptions', function (Blueprint $t) {
            $this->tenant($t);
            $t->foreignId('plan_id')->constrained('subscription_plans');
            $t->string('status');
            $t->date('start_date');
            $t->date('end_date')->nullable();
            $t->timestamp('trial_ends_at')->nullable();
            $t->timestamp('cancelled_at')->nullable();
            $t->decimal('amount', 20, 2);
            $t->string('currency', 3);
        });
        Schema::create('subscription_invoices', function (Blueprint $t) {
            $this->tenant($t);
            $this->link($t, 'subscription_id', 'subscriptions');
            $t->string('number')->unique();
            $t->decimal('amount', 20, 2);
            $t->string('currency', 3);
            $t->date('due_date');
            $t->string('status')->default('open');
        });
        Schema::create('subscription_payments', function (Blueprint $t) {
            $this->tenant($t);
            $this->link($t, 'subscription_invoice_id', 'subscription_invoices');
            $t->decimal('amount', 20, 2);
            $t->string('provider_reference')->unique();
            $t->timestamp('paid_at');
        });
    }

    public function down(): void
    {
        foreach (['subscription_payments', 'subscription_invoices', 'subscriptions', 'subscription_plans', 'exchange_rates', 'audit_logs', 'attachments', 'reconciliations', 'bank_transactions', 'reconciliation_sessions', 'expenses', 'expense_categories', 'customer_refunds', 'credit_note_items', 'credit_notes', 'bill_payments', 'payments', 'bill_items', 'invoice_items', 'products', 'bills', 'invoices', 'payment_methods', 'bank_accounts', 'journal_lines', 'journal_entries', 'document_sequences', 'financial_years', 'tax_rates', 'accounts', 'suppliers', 'customers', 'company_invitations', 'company_settings', 'company_users', 'role_permissions', 'permissions', 'roles', 'companies', 'currencies'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
