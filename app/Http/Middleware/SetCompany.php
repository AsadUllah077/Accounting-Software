<?php

namespace App\Http\Middleware;

use App\Services\Tenancy\CompanyContext;
use Closure;
use Illuminate\Http\Request;

class SetCompany
{
    public function handle(Request $request, Closure $next)
    {
        $context = app(CompanyContext::class);
        $context->clear();
        $company = $request->user()?->companies()->wherePivot('is_active', true)->where('companies.status', 'active')->find($request->session()->get('company_id'));
        if (! $company) {
            return redirect()->route('companies.index');
        }
        $context->set($company);
        try {
            return $next($request);
        } finally {
            $context->clear();
        }
    }
}
