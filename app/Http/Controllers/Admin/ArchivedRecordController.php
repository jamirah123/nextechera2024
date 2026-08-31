<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DeletedRecordSnapshot;
use App\Services\ArchiveService;
use App\Support\Archive\DeletedRecordRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

class ArchivedRecordController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', DeletedRecordSnapshot::class);

        $filters = $request->only(['q', 'type', 'status']);

        $records = DeletedRecordSnapshot::query()
            ->with(['deleter:id,name', 'restorer:id,name'])
            ->search($filters['q'] ?? null)
            ->when(filled($filters['type'] ?? null), fn ($query) => $query->where('record_type', $filters['type']))
            ->when(($filters['status'] ?? 'active') === 'active', fn ($query) => $query->active())
            ->when(($filters['status'] ?? '') === 'restored', fn ($query) => $query->whereNotNull('restored_at'))
            ->latest('created_at')
            ->paginate(table_per_page())
            ->withQueryString();

        return view('admin.archived.index', [
            'records' => $records,
            'filters' => $filters,
            'types' => DeletedRecordRegistry::types(),
            'stats' => [
                'active' => DeletedRecordSnapshot::query()->active()->count(),
                'restored' => DeletedRecordSnapshot::query()->whereNotNull('restored_at')->count(),
            ],
        ]);
    }

    public function show(DeletedRecordSnapshot $deletedRecordSnapshot): View
    {
        $this->authorize('view', $deletedRecordSnapshot);

        $deletedRecordSnapshot->load(['deleter:id,name,email', 'restorer:id,name,email']);

        return view('admin.archived.show', [
            'record' => $deletedRecordSnapshot,
        ]);
    }

    public function restore(DeletedRecordSnapshot $deletedRecordSnapshot, ArchiveService $archive): RedirectResponse
    {
        $this->authorize('restore', $deletedRecordSnapshot);

        try {
            $archive->restore($deletedRecordSnapshot, request()->user());
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['archive' => $e->getMessage()]);
        }

        return redirect()
            ->route('archived.index', ['status' => 'restored'])
            ->with('status', $deletedRecordSnapshot->label.' restored successfully.');
    }
}
