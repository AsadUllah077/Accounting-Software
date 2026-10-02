<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ContactImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasCompanyPermission('contact.create');
    }

    public function rules(): array
    {
        return ['file' => 'required|file|mimes:csv,txt|max:5120'];
    }
}
