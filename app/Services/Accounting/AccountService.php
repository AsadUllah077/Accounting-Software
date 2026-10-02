<?php

namespace App\Services\Accounting;

use App\Models\Account;
use App\Models\Company;
use App\Services\Audit\Audit;
use App\Services\Tenancy\CompanyContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AccountService
{
    public function save(array $data, ?Account $account = null): Account
    {
        return DB::transaction(function () use ($data, $account) {
            Company::lockForUpdate()->findOrFail(app(CompanyContext::class)->id());
            if (Account::where('code', $data['code'])->when($account, fn ($q) => $q->where('id', '<>', $account->id))->exists()) {
                $this->fail('Account code already exists.');
            }
            if ($account && $account->system_key && ($data['type'] !== $account->type || ! $data['is_active'])) {
                $this->fail('System control accounts must remain active with their original type.');
            }
            if ($account && $data['type'] !== $account->type && $account->lines()->exists()) {
                $this->fail('An account with accounting history cannot change type.');
            }
            $parentId = $data['parent_id'] ?? null;
            $visited = [];
            while ($parentId) {
                if (($account && (int) $parentId === $account->id) || in_array($parentId, $visited)) {
                    $this->fail('Account hierarchy cannot contain cycles.');
                }$visited[] = $parentId;
                $parent = Account::findOrFail($parentId);
                if ($parent->type !== $data['type']) {
                    $this->fail('Parent and child accounts must have the same type.');
                }$parentId = $parent->parent_id;
            }
            $old = $account?->toArray() ?? [];
            if ($account) {
                $account->update($data);
            } else {
                $account = Account::create($data);
            }Audit::record('account.saved', $account, $old, $data);

            return $account;
        });
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['account' => $message]);
    }
}
