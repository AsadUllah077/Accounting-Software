<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasCompanyPermission($this->isMethod('post') ? 'contact.create' : 'contact.update');
    }

    public function rules(): array
    {
        return ['name' => 'required|string|max:255', 'email' => 'nullable|email|max:255', 'phone' => 'nullable|string|max:50', 'address' => 'nullable|string|max:4000', 'tax_number' => 'nullable|string|max:50', 'status' => 'required|in:active,archived'];
    }
}
