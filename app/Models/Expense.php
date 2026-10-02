<?php

namespace App\Models;

use App\Models\Concerns\ImmutableWhenPosted;

class Expense extends TenantModel
{
    use ImmutableWhenPosted;

    public function attachments()
    {
        return $this->hasMany(Attachment::class, 'model_id')->where('model_type', 'expense');
    }

    public function category()
    {
        return $this->belongsTo(ExpenseCategory::class, 'category_id');
    }

    public function bankAccount()
    {
        return $this->belongsTo(BankAccount::class, 'bank_account_id');
    }

    public function journal()
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }
}
