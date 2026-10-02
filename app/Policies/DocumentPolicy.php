<?php

namespace App\Policies;

use App\Models\Document;
use App\Models\Invoice;
use App\Models\User;
use App\Services\Tenancy\CompanyContext;

class DocumentPolicy
{
    private function permits(User $user, Document $record, string $action): bool
    {
        return (int) $record->company_id === app(CompanyContext::class)->id() && $user->hasCompanyPermission(($record instanceof Invoice ? 'invoice.' : 'bill.').$action);
    }

    public function view(User $user, Document $record): bool
    {
        return $this->permits($user, $record, 'view');
    }

    public function approve(User $user, Document $record): bool
    {
        return $this->permits($user, $record, 'approve');
    }

    public function pay(User $user, Document $record): bool
    {
        return $this->permits($user, $record, 'pay');
    }

    public function update(User $user, Document $record): bool
    {
        return $record->status === 'draft' && $this->permits($user,$record,'update');
    }
}
