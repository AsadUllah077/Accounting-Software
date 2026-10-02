<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasCompanyPermission('expense.create');
    }

    public function rules(): array
    {
        return ['idempotency_key' => 'required|string|max:100', 'category_id' => 'required|integer', 'bank_account_id' => 'required|integer', 'tax_rate_id' => 'nullable|integer', 'date' => 'required|date_format:Y-m-d', 'description' => 'required|string|max:2000', 'amount' => 'required|decimal:0,2|gt:0|max:1000000000'];
    }
}
