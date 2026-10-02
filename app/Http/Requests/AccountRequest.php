<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasCompanyPermission('settings.manage');
    }

    public function rules(): array
    {
        return ['code' => 'required|string|max:50', 'name' => 'required|string|max:255', 'type' => 'required|in:asset,liability,equity,revenue,expense', 'parent_id' => 'nullable|integer', 'is_active' => 'required|boolean', 'cash_flow_class' => 'required|in:operating,investing,financing'];
    }
}
