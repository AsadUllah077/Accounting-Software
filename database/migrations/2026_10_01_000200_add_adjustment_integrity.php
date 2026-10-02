<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('credit_note_items', function (Blueprint $t) {
            $t->unsignedBigInteger('invoice_item_id');
            $t->foreign(['company_id', 'invoice_item_id'])->references(['company_id', 'id'])->on('invoice_items')->restrictOnDelete();
        });
        Schema::table('customer_refunds', fn (Blueprint $t) => $t->string('request_hash', 64));
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }
        DB::statement('ALTER TABLE journal_lines ADD CONSTRAINT journal_line_positive_side CHECK ((debit > 0 AND credit = 0) OR (credit > 0 AND debit = 0))');
        DB::statement("ALTER TABLE accounts ADD CONSTRAINT valid_account_type CHECK (type IN ('asset','liability','equity','revenue','expense'))");
        DB::statement('ALTER TABLE tax_rates ADD CONSTRAINT nonnegative_tax CHECK (rate >= 0 AND rate <= 100)');
        DB::statement('ALTER TABLE financial_years ADD CONSTRAINT valid_financial_year CHECK (start_date <= end_date)');
        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION guard_posted_journal() RETURNS trigger LANGUAGE plpgsql AS $$
DECLARE debit_total numeric; credit_total numeric; line_count integer; lock_date date;
BEGIN
    IF TG_OP <> 'INSERT' AND OLD.status = 'posted' THEN
        RAISE EXCEPTION 'Posted journals are immutable';
    END IF;
    IF TG_OP = 'DELETE' THEN RETURN OLD; END IF;
    IF NEW.status = 'posted' THEN
        SELECT SUM(debit), SUM(credit), COUNT(*) INTO debit_total, credit_total, line_count FROM journal_lines WHERE journal_entry_id = NEW.id AND company_id = NEW.company_id;
        IF line_count < 2 OR debit_total <> credit_total THEN RAISE EXCEPTION 'Posted journals must have two or more balanced lines'; END IF;
        SELECT locked_through INTO lock_date FROM companies WHERE id = NEW.company_id FOR UPDATE;
        IF NEW.date <= lock_date OR EXISTS(SELECT 1 FROM financial_years WHERE company_id=NEW.company_id AND is_closed AND NEW.date BETWEEN start_date AND end_date) THEN RAISE EXCEPTION 'Accounting period is locked'; END IF;
        IF EXISTS(SELECT 1 FROM journal_lines l JOIN accounts a ON a.id=l.account_id AND a.company_id=l.company_id WHERE l.journal_entry_id=NEW.id AND NOT a.is_active) THEN RAISE EXCEPTION 'Account is inactive'; END IF;
    END IF;
    RETURN NEW;
END $$;
CREATE TRIGGER journal_guard BEFORE INSERT OR UPDATE OR DELETE ON journal_entries FOR EACH ROW EXECUTE FUNCTION guard_posted_journal();
CREATE OR REPLACE FUNCTION guard_journal_line() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
    IF TG_OP <> 'INSERT' AND EXISTS(SELECT 1 FROM journal_entries WHERE id=OLD.journal_entry_id AND company_id=OLD.company_id AND status='posted') THEN RAISE EXCEPTION 'Posted journal lines are immutable'; END IF;
    IF TG_OP <> 'DELETE' AND EXISTS(SELECT 1 FROM journal_entries WHERE id=NEW.journal_entry_id AND company_id=NEW.company_id AND status='posted') THEN RAISE EXCEPTION 'Posted journal lines are immutable'; END IF;
    IF TG_OP = 'DELETE' THEN RETURN OLD; END IF;
    RETURN NEW;
END $$;
CREATE TRIGGER journal_line_guard BEFORE INSERT OR UPDATE OR DELETE ON journal_lines FOR EACH ROW EXECUTE FUNCTION guard_journal_line();
CREATE OR REPLACE FUNCTION guard_audit_log() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN RAISE EXCEPTION 'Audit logs are append-only'; END $$;
CREATE TRIGGER audit_guard BEFORE UPDATE OR DELETE ON audit_logs FOR EACH ROW EXECUTE FUNCTION guard_audit_log();
SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared('DROP TRIGGER IF EXISTS audit_guard ON audit_logs; DROP FUNCTION IF EXISTS guard_audit_log(); DROP TRIGGER IF EXISTS journal_line_guard ON journal_lines; DROP FUNCTION IF EXISTS guard_journal_line(); DROP TRIGGER IF EXISTS journal_guard ON journal_entries; DROP FUNCTION IF EXISTS guard_posted_journal();');
            foreach (['journal_lines' => 'journal_line_positive_side', 'accounts' => 'valid_account_type', 'tax_rates' => 'nonnegative_tax', 'financial_years' => 'valid_financial_year'] as $table => $constraint) {
                DB::statement("ALTER TABLE {$table} DROP CONSTRAINT {$constraint}");
            }
        }
        Schema::table('credit_note_items', function (Blueprint $t) {
            $t->dropForeign(['company_id', 'invoice_item_id']);
            $t->dropColumn('invoice_item_id');
        });
        Schema::table('customer_refunds', fn (Blueprint $t) => $t->dropColumn('request_hash'));
    }
};
