<?php

namespace App\Http\Controllers;

use App\Http\Requests\ContactImportRequest;
use App\Models\Customer;
use App\Models\Supplier;
use App\Services\Audit\Audit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ContactImportController extends Controller
{
    public function store(ContactImportRequest $request, string $kind)
    {
        abort_unless(in_array($kind, ['customers', 'suppliers']), 404);
        $class = $kind === 'customers' ? Customer::class : Supplier::class;
        $handle = fopen($request->file('file')->getRealPath(), 'r');
        $rows = [];
        try {
            $header = fgetcsv($handle, 0, ',', '"', '');
            if ($header !== ['name', 'email', 'phone', 'address', 'tax_number']) {
                throw ValidationException::withMessages(['file' => 'Use CSV columns name,email,phone,address,tax_number.']);
            }
            while (($cells = fgetcsv($handle, 0, ',', '"', '')) !== false) {
                if ($cells === [null]) {
                    continue;
                }if (count($cells) !== 5 || count($rows) >= 10000) {
                    throw ValidationException::withMessages(['file' => 'Invalid row or more than 10,000 contacts.']);
                }$row = array_combine($header, array_map(fn ($value) => trim($value) === '' ? null : trim($value), $cells));
                Validator::make($row, ['name' => 'required|string|max:255', 'email' => 'nullable|email|max:255', 'phone' => 'nullable|string|max:50', 'address' => 'nullable|string|max:4000', 'tax_number' => 'nullable|string|max:50'])->validate();
                $rows[] = $row;
            }
        } finally {
            fclose($handle);
        }
        $count = DB::transaction(function () use ($rows, $class) {
            $count = 0;
            foreach ($rows as $row) {
                $contact = $class::firstOrCreate(['name' => $row['name'], 'email' => $row['email']], $row + ['status' => 'active']);
                if ($contact->wasRecentlyCreated) {
                    $count++;
                    Audit::record('contact.imported', $contact);
                }
            }

return $count;
        });

        return back()->with('success',"Imported {$count} new contacts. Existing name/email pairs were skipped.");
    }
}
