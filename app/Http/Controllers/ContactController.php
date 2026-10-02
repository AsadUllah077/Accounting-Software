<?php

namespace App\Http\Controllers;

use App\Http\Requests\ContactRequest;
use App\Models\Customer;
use App\Models\Supplier;
use App\Services\Audit\Audit;
use App\Services\Reporting\OperationalReports;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class ContactController extends Controller
{
    private function model(string $kind): string
    {
        abort_unless(in_array($kind, ['customers', 'suppliers']), 404);

        return $kind === 'customers' ? Customer::class : Supplier::class;
    }

    public function index(Request $request, string $kind)
    {
        Gate::authorize('permission', 'contact.view');
        $search = mb_substr((string) $request->query('search', ''), 0, 100);
        $contacts = $this->model($kind)::when($search, fn ($q) => $q->where('name', 'like', '%'.$search.'%'))->orderBy('name')->paginate(15)->withQueryString();

        return Inertia::render('Contacts/Index', compact('contacts', 'kind', 'search'));
    }

    public function store(ContactRequest $request, string $kind)
    {
        DB::transaction(function () use ($request, $kind) {
            $contact = $this->model($kind)::create($request->validated());
            Audit::record('contact.created', $contact, [], $request->validated());
        });

        return back()->with('success', 'Contact created.');
    }

    public function update(ContactRequest $request, string $kind, int $id)
    {
        DB::transaction(function () use ($request, $kind, $id) {
            $contact = $this->model($kind)::findOrFail($id);
            $old = $contact->only(array_keys($request->validated()));
            $contact->update($request->validated());
            Audit::record('contact.updated', $contact, $old, $request->validated());
        });

        return back()->with('success', 'Contact updated.');
    }

    public function destroy(string $kind, int $id)
    {
        Gate::authorize('permission', 'contact.delete');
        DB::transaction(function () use ($kind, $id) {
            $contact = $this->model($kind)::findOrFail($id);
            $contact->update(['status' => 'archived']);
            Audit::record('contact.archived', $contact);
        });

        return back()->with('success', 'Contact archived.');
    }

    public function show(string $kind, int $id)
    {
        Gate::authorize('permission', 'contact.view');
        $contact = $this->model($kind)::findOrFail($id);
        $records = ($kind === 'customers' ? $contact->invoices() : $contact->bills())->orderByDesc('id')->paginate(15);
        $statement = app(OperationalReports::class)->statement($kind === 'customers' ? 'customer' : 'supplier', $contact->id, '1900-01-01', now()->toDateString());
        $balance = $statement['closing_balance'];

        return Inertia::render('Contacts/Show', compact('contact', 'records', 'kind', 'balance'));
    }
}
