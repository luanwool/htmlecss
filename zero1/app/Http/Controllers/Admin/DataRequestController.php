<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DataRequest;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DataRequestController extends Controller
{
    public function index(): View
    {
        return view('admin.privacy.index', ['requests' => DataRequest::with('user')->latest()->paginate(30)]);
    }

    public function update(Request $request, DataRequest $dataRequest, AuditLogger $audit): RedirectResponse
    {
        $v = $request->validate([
            'status' => ['required', Rule::in(array_keys(DataRequest::STATUSES))],
            'resolution_note' => ['nullable', 'string', 'max:2000', 'required_if:status,rejected'],
        ]);
        $original = $dataRequest->getOriginal();
        $dataRequest->update($v + ['handled_by' => $request->user()->id, 'handled_at' => now()]);
        $audit->logChanges('privacy.request_updated', $dataRequest, $original);

        return back()->with('status', 'Solicitação atualizada.');
    }
}
