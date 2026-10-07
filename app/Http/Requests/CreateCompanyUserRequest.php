<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class CreateCompanyUserRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['email' => strtolower(trim((string) $this->input('email')))]);
    }

    public function authorize(): bool
    {
        return $this->user()->hasCompanyPermission('users.manage');
    }

    public function rules(): array
    {
        return ['name' => 'required|string|max:255', 'email' => 'required|email|max:255|unique:users,email', 'password' => ['required', 'confirmed', Password::min(12)->letters()->numbers()], 'role_id' => 'required|integer|exists:roles,id'];
    }

    public function messages(): array
    {
        return ['email.unique' => 'An account already uses this email. Invite that person instead of creating another account.'];
    }
}
