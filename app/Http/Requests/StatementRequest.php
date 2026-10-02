<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StatementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasCompanyPermission('banking.reconcile');
    }

    public function rules(): array
    {
        return ['bank_account_id' => 'required|integer', 'file' => 'required|file|mimes:csv,txt|max:5120'];
    }
}
