<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasCompanyPermission('report.view');
    }

    public function rules(): array
    {
        return ['from' => 'nullable|date_format:Y-m-d', 'to' => 'nullable|date_format:Y-m-d|after_or_equal:from', 'contact_id' => 'nullable|integer', 'account_id' => 'nullable|integer', 'format' => 'nullable|in:pdf,xlsx'];
    }

    public function dates(): array
    {
        $data = $this->validated();

        return [$data['from'] ?? now()->startOfYear()->toDateString(), $data['to'] ?? now()->toDateString()];
    }
}
