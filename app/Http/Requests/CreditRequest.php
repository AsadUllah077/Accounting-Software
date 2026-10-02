<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreditRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasCompanyPermission('invoice.approve');
    }

    public function rules(): array
    {
        return ['date' => 'required|date_format:Y-m-d', 'reason' => 'required|string|max:2000', 'items' => 'required|array|min:1|max:100', 'items.*.invoice_item_id' => 'required|integer|distinct', 'items.*.amount' => 'required|decimal:0,2|gt:0|max:1000000000'];
    }
}
