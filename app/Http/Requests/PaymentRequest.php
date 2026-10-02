<?php

namespace App\Http\Requests;

use App\Services\Tenancy\CompanyContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasCompanyPermission($this->route('kind') === 'invoices' ? 'invoice.pay' : 'bill.pay');
    }

    public function rules(): array
    {
        return ['amount' => 'required|decimal:0,2|gt:0|max:1000000000', 'paid_at' => 'required|date_format:Y-m-d', 'bank_account_id' => ['required', 'integer', Rule::exists('bank_accounts', 'id')->where('company_id', app(CompanyContext::class)->id())], 'reference' => 'nullable|string|max:255', 'idempotency_key' => 'required|string|max:100'];
    }
}
