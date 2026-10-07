<?php

namespace App\Http\Controllers;

use App\Models\Subscription;
use App\Models\SubscriptionInvoice;
use App\Models\SubscriptionPlan;
use App\Services\Subscriptions\SubscriptionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class SubscriptionController extends Controller
{
    public function index()
    {
        Gate::authorize('permission', 'settings.manage');

        return Inertia::render('Settings/Subscription', ['plans' => SubscriptionPlan::where('is_active', true)->get(), 'subscriptions' => Subscription::with('plan')->latest('id')->get(), 'invoices' => SubscriptionInvoice::latest('id')->paginate(15), 'platformAdmin' => (bool) auth()->user()->is_platform_admin]);
    }

    public function choose(Request $request, SubscriptionService $service)
    {
        Gate::authorize('permission', 'settings.manage');
        $data = $request->validate(['plan_id' => 'required|integer|exists:subscription_plans,id']);
        $service->choose($data['plan_id']);

        return back()->with('success', 'Subscription updated.');
    }

    public function cancel(SubscriptionService $service)
    {
        Gate::authorize('permission', 'settings.manage');
        $service->cancel();

        return back()->with('success', 'Subscription cancelled.');
    }

    public function payment(Request $request, int $id, SubscriptionService $service)
    {
        abort_unless($request->user()->is_platform_admin, 403);
        $data = $request->validate(['reference' => 'required|string|max:255']);
        $service->recordPayment(SubscriptionInvoice::findOrFail($id), $data['reference']);

        return back()->with('success', 'Verified subscription payment recorded.');
    }
}
