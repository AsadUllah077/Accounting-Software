<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasCompanyPermission('settings.manage');
    }

    public function rules(): array
    {
        return ['name' => 'required|string|max:255', 'legal_name' => 'nullable|string|max:255', 'email' => 'nullable|email|max:255', 'phone' => 'nullable|string|max:50', 'timezone' => 'required|timezone', 'locked_through' => 'nullable|date_format:Y-m-d'];
    }
}
