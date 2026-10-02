<?php

namespace App\Http\Middleware;

use App\Services\Tenancy\CompanyPermissions;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user(),
            ],
            'companies' => fn () => $request->user()?->companies()->wherePivot('is_active', true)->get(['companies.id', 'companies.name']) ?? [],
            'company' => function () use ($request) {
                return $request->user()?->companies()->wherePivot('is_active', true)->find($request->session()->get('company_id'));
            },
            'permissions' => function () use ($request) {
                if (! $request->user() || ! $request->session()->has('company_id')) {
                    return [];
                }

                return app(CompanyPermissions::class)->names($request->user(), (int) $request->session()->get('company_id'));
            },
            'flash' => ['success' => fn () => $request->session()->get('success')],
        ];
    }
}
