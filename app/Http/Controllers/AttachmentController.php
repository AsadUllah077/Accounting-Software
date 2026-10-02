<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Models\Bill;
use App\Models\Expense;
use App\Models\Invoice;
use App\Services\Audit\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class AttachmentController extends Controller
{
    private function target(string $kind, int $id, string $action)
    {
        $classes = ['invoice' => Invoice::class, 'bill' => Bill::class, 'expense' => Expense::class];
        abort_unless(isset($classes[$kind]), 404);
        Gate::authorize('permission', $kind.'.'.($action === 'view' ? 'view' : ($kind === 'expense' ? 'create' : 'update')));

        return $classes[$kind]::findOrFail($id);
    }

    public function store(Request $request, string $kind, int $id)
    {
        $target = $this->target($kind, $id, 'create');
        $request->validate(['file' => 'required|file|mimes:pdf,jpg,jpeg,png,webp|max:10240']);
        $file = $request->file('file');
        $disk = config('filesystems.attachments_disk', 'local');
        $path = $file->store('companies/'.$target->company_id.'/attachments', $disk);
        try {
            $attachment = Attachment::create(['model_type' => $kind, 'model_id' => $target->id, 'disk' => $disk, 'file_path' => $path, 'file_name' => mb_substr(basename($file->getClientOriginalName()), 0, 255), 'mime_type' => $file->getMimeType(), 'size' => $file->getSize()]);
            Audit::record('attachment.uploaded', $attachment);
        } catch (\Throwable $e) {
            Storage::disk($disk)->delete($path);
            throw $e;
        }

        return back()->with('success', 'Private attachment uploaded.');
    }

    public function download(int $id)
    {
        $attachment = Attachment::findOrFail($id);
        $this->target($attachment->model_type, $attachment->model_id, 'view');

        return Storage::disk($attachment->disk)->download($attachment->file_path, $attachment->file_name, ['X-Content-Type-Options' => 'nosniff']);
    }
}
