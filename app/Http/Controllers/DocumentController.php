<?php

namespace App\Http\Controllers;

use App\Http\Requests\DocumentRequest;
use App\Http\Requests\PaymentRequest;
use App\Models\Account;
use App\Models\Attachment;
use App\Models\BankAccount;
use App\Models\Bill;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Supplier;
use App\Models\TaxRate;
use App\Services\Invoicing\DocumentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class DocumentController extends Controller
{
    private function model(string $kind): string
    {
        abort_unless(in_array($kind, ['invoices', 'bills']), 404);

        return $kind === 'invoices' ? Invoice::class : Bill::class;
    }

    private function permission(string $kind, string $action): void
    {
        Gate::authorize('permission', ($kind === 'invoices' ? 'invoice.' : 'bill.').$action);
    }

    public function index(Request $request, string $kind)
    {
        $this->permission($kind, 'view');
        $sales = $kind === 'invoices';
        $relation = $sales ? 'customer' : 'supplier';
        $search = mb_substr((string) $request->query('search', ''), 0, 100);
        $contact = mb_substr((string) $request->query('contact', ''), 0, 100);
        $request->validate(['contact_id' => 'nullable|integer|min:1']);
        $contactId = $request->query('contact_id');
        $selectedContact = $contactId ? ($sales ? Customer::class : Supplier::class)::findOrFail($contactId, ['id', 'name']) : null;
        $status = $request->query('status');
        $documents = $this->model($kind)::with($relation)
            ->when($contactId, fn ($q) => $q->where($sales ? 'customer_id' : 'supplier_id', $contactId))
            ->when($search, fn ($q) => $q->where($sales ? 'invoice_number' : 'bill_number', 'like', '%'.$search.'%'))
            ->when($contact, fn ($q) => $q->whereHas($relation, fn ($contactQuery) => $contactQuery->where('name', 'like', '%'.$contact.'%')))
            ->when(in_array($status, ['draft', 'approved', 'partially_paid', 'paid']), fn ($q) => $q->where('status', $status))
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Documents/Index', compact('documents', 'kind', 'search', 'contact', 'status', 'selectedContact'));
    }

    public function contactOptions(Request $request, string $kind)
    {
        $this->permission($kind, 'view');
        $data = $request->validate(['search' => 'nullable|string|max:100', 'after' => 'nullable|integer|min:1']);
        $contacts = ($kind === 'invoices' ? Customer::class : Supplier::class)::query()
            ->when($data['search'] ?? '', fn ($q, $search) => $q->where('name', 'like', '%'.$search.'%'))
            ->when($data['after'] ?? null, fn ($q, $after) => $q->where('id', '>', $after))
            ->orderBy('id')
            ->limit(26)
            ->get(['id', 'name']);

        return response()->json([
            'data' => $contacts->take(25)->values(),
            'next' => $contacts->count() > 25 ? $contacts[24]->id : null,
        ]);
    }

    public function create(string $kind)
    {
        $this->permission($kind, 'create');
        $sales = $kind === 'invoices';

        return Inertia::render('Documents/Create', ['kind' => $kind, 'contacts' => ($sales ? Customer::class : Supplier::class)::where('status', 'active')->orderBy('name')->get(['id', 'name']), 'accounts' => Account::whereIn('type', $sales ? ['revenue'] : ['expense', 'asset'])->where('is_active', true)->get(['id', 'code', 'name']), 'taxRates' => TaxRate::where('is_active', true)->get(['id', 'name', 'rate'])]);
    }

    public function store(DocumentRequest $request, string $kind, DocumentService $service)
    {
        $doc = $service->create($kind === 'invoices' ? 'invoice' : 'bill', $request->validated());

        return redirect()->route('documents.show', [$kind, $doc->id])->with('success', 'Draft created.');
    }

    public function edit(string $kind, int $id)
    {
        $document = $this->model($kind)::with('items')->findOrFail($id);
        Gate::authorize('update', $document);
        $sales = $kind === 'invoices';

        return Inertia::render('Documents/Create', ['kind' => $kind, 'document' => $document, 'contacts' => ($sales ? Customer::class : Supplier::class)::where('status', 'active')->get(['id', 'name']), 'accounts' => Account::whereIn('type', $sales ? ['revenue'] : ['expense', 'asset'])->where('is_active', true)->get(['id', 'code', 'name']), 'taxRates' => TaxRate::where('is_active', true)->get(['id', 'name', 'rate'])]);
    }

    public function update(DocumentRequest $request, string $kind, int $id, DocumentService $service)
    {
        $doc = $this->model($kind)::findOrFail($id);
        Gate::authorize('update', $doc);
        $service->updateDraft($doc, $request->validated());

        return redirect()->route('documents.show', [$kind, $id])->with('success', 'Draft updated.');
    }

    public function void(Request $request, string $kind, int $id, DocumentService $service)
    {
        $this->permission($kind, 'void');
        $data = $request->validate(['date' => 'required|date_format:Y-m-d']);
        $service->void($this->model($kind)::findOrFail($id), $data['date'], $request->user()->id);

        return back()->with('success', 'Document voided with a reversal journal.');
    }

    public function show(string $kind, int $id)
    {
        $this->permission($kind, 'view');
        $doc = $this->model($kind)::with(['items.account', 'payments', $kind === 'invoices' ? 'customer' : 'supplier'])->findOrFail($id);

        Gate::authorize('view', $doc);
        $files = Attachment::where('model_type', $kind === 'invoices' ? 'invoice' : 'bill')->where('model_id', $id)->get(['id', 'file_name']);

        return Inertia::render('Documents/Show', ['kind' => $kind, 'document' => $doc, 'banks' => BankAccount::get(['id', 'name']), 'files' => $files]);
    }

    public function approve(string $kind, int $id, DocumentService $service)
    {
        $this->permission($kind, 'approve');
        $service->approve($this->model($kind)::findOrFail($id), auth()->id());

        return back()->with('success', 'Document posted to the ledger.');
    }

    public function pay(PaymentRequest $request, string $kind, int $id, DocumentService $service)
    {
        $service->pay($this->model($kind)::findOrFail($id), $request->validated(), $request->user()->id);

        return back()->with('success', 'Payment recorded.');
    }
}
