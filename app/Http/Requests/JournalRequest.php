<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class JournalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasCompanyPermission('accounting.post');
    }

    public function rules(): array
    {
        return ['date' => 'required|date_format:Y-m-d', 'reference' => 'required|string|max:100', 'lines' => 'required|array|min:2|max:100', 'lines.*.account_id' => 'required|integer', 'lines.*.debit' => 'required|decimal:0,2|min:0|max:1000000000', 'lines.*.credit' => 'required|decimal:0,2|min:0|max:1000000000'];
    }
}
