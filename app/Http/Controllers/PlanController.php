<?php

namespace App\Http\Controllers;

use App\Models\SubscriptionPlan;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PlanController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->is_platform_admin, 403);

        return Inertia::render('Settings/Plans', ['plans' => SubscriptionPlan::orderBy('id')->get()]);
    }

    public function save(Request $request, ?int $id = null)
    {
        abort_unless($request->user()->is_platform_admin, 403);
        $data = $request->validate(['name' => 'required|string|max:100', 'description' => 'nullable|string|max:2000', 'price' => 'required|decimal:0,2|min:0', 'currency' => 'required|string|size:3|exists:currencies,code', 'interval' => 'required|in:monthly,yearly', 'features' => 'required|array', 'features.users' => 'required|integer|min:1|max:100000', 'features.invoices_per_month' => 'required|integer|min:1|max:10000000', 'is_active' => 'required|boolean']);
        if ($id) {
            SubscriptionPlan::findOrFail($id)->update($data);
        } else {
            SubscriptionPlan::create($data);
        }

return back()->with('success', 'Plan saved. Existing subscriptions retain their price snapshots.');
    }
}
