<?php

namespace App\Http\Requests;

use App\Services\Tenancy\CompanyContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasCompanyPermission(($this->route('kind') === 'invoices' ? 'invoice.' : 'bill.').($this->isMethod('put') ? 'update' : 'create'));
    }

    public function rules(): array
    {
        $sales = $this->route('kind') === 'invoices';
        $company = app(CompanyContext::class)->id();

        return [$sales ? 'customer_id' : 'supplier_id' => ['required', 'integer', Rule::exists($sales ? 'customers' : 'suppliers', 'id')->where('company_id', $company)->where('status', 'active')],
            'date' => 'required|date_format:Y-m-d', 'due_date' => 'required|date_format:Y-m-d|after_or_equal:date', 'notes' => 'nullable|string|max:5000', 'items' => 'required|array|min:1|max:100',
            'items.*.description' => 'required|string|max:1000', 'items.*.quantity' => 'required|decimal:0,4|gt:0|max:1000000', 'items.*.unit_price' => 'required|decimal:0,4|min:0|max:1000000000',
            'items.*.discount' => 'nullable|decimal:0,2|min:0', 'items.*.account_id' => ['required', 'integer', Rule::exists('accounts', 'id')->where('company_id', $company)],
            'items.*.tax_rate_id' => ['nullable', 'integer', Rule::exists('tax_rates', 'id')->where('company_id', $company)]];
    }
}
