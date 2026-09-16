<?php

namespace App\Http\Controllers\Organization;

use App\Enums\ContractStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organization\StoreClientRequest;
use App\Http\Requests\Organization\UpdateClientRequest;
use App\Models\Client;
use App\Services\EntityRelatedRecordsService;
use App\Services\EntityTimelineService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ClientController extends Controller
{
    public function __construct(
        private EntityTimelineService $timeline,
        private EntityRelatedRecordsService $relatedRecords,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Client::class);

        $clients = Client::query()
            ->withCount('sites')
            ->search($request->string('q')->toString())
            ->when($request->filled('contract_status'), fn ($q) => $q->where('contract_status', $request->string('contract_status')))
            ->latest()
            ->paginate(table_per_page())
            ->withQueryString();

        return view('organization.clients.index', [
            'clients' => $clients,
            'statuses' => ContractStatus::cases(),
            'filters' => $request->only(['q', 'contract_status']),
            'canManage' => $request->user()->can('create', Client::class),
            'canDelete' => $request->user()->can('deleteAny', Client::class),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Client::class);

        return view('organization.clients.create', [
            'statuses' => ContractStatus::cases(),
        ]);
    }

    public function store(StoreClientRequest $request): RedirectResponse
    {
        $client = Client::query()->create($request->validated());

        return redirect()
            ->route('clients.show', $client)
            ->with('status', 'Client registered successfully.');
    }

    public function show(Client $client): View
    {
        $this->authorize('view', $client);

        $client->load([
            'sites' => fn ($q) => $q->with(['region', 'supervisor'])->latest(),
            'creator',
            'updater',
        ]);

        return view('organization.clients.show', [
            'client' => $client,
            'canManage' => request()->user()->can('update', $client),
            'canDelete' => request()->user()->can('delete', $client),
            'timeline' => $this->timeline->for($client, request()->user()),
            'relatedPanels' => $this->relatedRecords->for($client),
            'lifecycle' => [
                'steps' => [
                    ['label' => 'Pending'],
                    ['label' => 'Active'],
                    ['label' => 'Renewal / review'],
                ],
                'current' => match ($client->contract_status) {
                    ContractStatus::Pending => 0,
                    ContractStatus::Active => 1,
                    ContractStatus::Expired, ContractStatus::Suspended => 2,
                    default => 2,
                },
                'terminal' => $client->contract_status === ContractStatus::Terminated ? 'Terminated' : null,
                'terminal_tone' => 'rose',
            ],
        ]);
    }

    public function edit(Client $client): View
    {
        $this->authorize('update', $client);

        return view('organization.clients.edit', [
            'client' => $client,
            'statuses' => ContractStatus::cases(),
        ]);
    }

    public function update(UpdateClientRequest $request, Client $client): RedirectResponse
    {
        $client->update($request->validated());

        return redirect()
            ->route('clients.show', $client)
            ->with('status', 'Client updated successfully.');
    }

    public function destroy(Client $client): RedirectResponse
    {
        $this->authorize('delete', $client);

        if ($client->sites()->exists()) {
            return back()->withErrors([
                'client' => 'Cannot archive a client that still has security sites. Close or reassign sites first.',
            ]);
        }

        $client->delete();

        return redirect()
            ->route('clients.index')
            ->with('status', 'Client archived successfully.');
    }
}
