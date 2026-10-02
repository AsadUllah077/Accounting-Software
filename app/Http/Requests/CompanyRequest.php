<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return ['name' => 'required|string|max:255', 'currency' => ['required', Rule::exists('currencies', 'code')->where('is_active', true)], 'timezone' => 'required|timezone', 'financial_year_start_date' => 'required|date', 'email' => 'nullable|email|max:255'];
    }
}
