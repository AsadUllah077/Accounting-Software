<?php

namespace App\Http\Controllers;

use App\Http\Requests\CompanyRequest;
use App\Models\Currency;
use App\Services\Tenancy\CreateCompany;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CompanyController extends Controller
{
    public function index(Request $request)
    {
        return Inertia::render('Companies/Index', ['companies' => $request->user()->companies()->wherePivot('is_active', true)->get(), 'currencies' => Currency::where('is_active', true)->get()]);
    }

    public function store(CompanyRequest $request, CreateCompany $action)
    {
        $company = $action->execute($request->user(), $request->validated());
        $request->session()->put('company_id', $company->id);

        return redirect()->route('dashboard');
    }

    public function switch(Request $request, int $company)
    {
        $record = $request->user()->companies()->wherePivot('is_active', true)->where('companies.status', 'active')->findOrFail($company);
        $request->session()->put('company_id', $record->id);

        return redirect()->route('dashboard');
    }
}
