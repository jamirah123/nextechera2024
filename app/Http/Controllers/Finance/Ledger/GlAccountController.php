<?php

namespace App\Http\Controllers\Finance\Ledger;

use App\Enums\GlAccountType;
use App\Http\Controllers\Controller;
use App\Models\GlAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class GlAccountController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewFinance');

        $accounts = GlAccount::query()
            ->when($request->filled('q'), fn ($q) => $q->search($request->string('q')->toString()))
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->string('type')->toString()))
            ->orderBy('code')
            ->paginate(table_per_page())
            ->withQueryString();

        return view('finance.ledger.accounts.index', [
            'accounts' => $accounts,
            'types' => GlAccountType::cases(),
            'filters' => $request->only(['q', 'type']),
            'canManage' => $request->user()->can('manageFinance'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('manageFinance');

        $data = $request->validate([
            'code' => ['required', 'string', 'max:20', 'unique:gl_accounts,code'],
            'name' => ['required', 'string', 'max:120'],
            'type' => ['required', Rule::in(GlAccountType::values())],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        GlAccount::query()->create([
            ...$data,
            'is_postable' => true,
            'is_active' => true,
        ]);

        return redirect()
            ->route('ledger.accounts.index')
            ->with('status', 'GL account '.$data['code'].' created.');
    }
}
