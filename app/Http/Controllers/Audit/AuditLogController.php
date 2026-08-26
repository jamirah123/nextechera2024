<?php

namespace App\Http\Controllers\Audit;

use App\Enums\AuditCategory;
use App\Enums\AuditSeverity;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Services\ReportExportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AuditLogController extends Controller
{
    public function __construct(private ReportExportService $exports)
    {
    }

    public function index(Request $request): View
    {
        Gate::authorize('viewAuditLogs');

        $filters = $request->only(['q', 'category', 'severity', 'overrides', 'from', 'to']);

        $logs = AuditLog::query()
            ->with('actor:id,name')
            ->search($filters['q'] ?? null)
            ->when(! empty($filters['category']), fn ($q) => $q->where('category', $filters['category']))
            ->when(! empty($filters['severity']), fn ($q) => $q->where('severity', $filters['severity']))
            ->when($request->boolean('overrides'), fn ($q) => $q->overrides())
            ->when(! empty($filters['from']), fn ($q) => $q->whereDate('created_at', '>=', $filters['from']))
            ->when(! empty($filters['to']), fn ($q) => $q->whereDate('created_at', '<=', $filters['to']))
            ->latest('created_at')
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return view('audit.index', [
            'logs' => $logs,
            'filters' => $filters,
            'categories' => AuditCategory::cases(),
            'severities' => AuditSeverity::cases(),
            'stats' => [
                'total' => AuditLog::query()->count(),
                'overrides' => AuditLog::query()->overrides()->count(),
                'critical' => AuditLog::query()->where('severity', AuditSeverity::Critical)->count(),
                'today' => AuditLog::query()->whereDate('created_at', now()->toDateString())->count(),
            ],
            'exportQuery' => array_filter([
                ...$filters,
                'overrides' => $request->boolean('overrides') ? '1' : null,
            ], fn ($v) => $v !== null && $v !== ''),
        ]);
    }

    public function show(AuditLog $auditLog): View
    {
        Gate::authorize('viewAuditLogs');

        $auditLog->load(['actor:id,name,email', 'subject']);

        return view('audit.show', ['log' => $auditLog]);
    }

    public function export(Request $request): StreamedResponse
    {
        Gate::authorize('viewAuditLogs');

        $filters = $request->only(['q', 'category', 'severity', 'from', 'to']);

        $rows = AuditLog::query()
            ->search($filters['q'] ?? null)
            ->when(! empty($filters['category']), fn ($q) => $q->where('category', $filters['category']))
            ->when(! empty($filters['severity']), fn ($q) => $q->where('severity', $filters['severity']))
            ->when($request->boolean('overrides'), fn ($q) => $q->overrides())
            ->when(! empty($filters['from']), fn ($q) => $q->whereDate('created_at', '>=', $filters['from']))
            ->when(! empty($filters['to']), fn ($q) => $q->whereDate('created_at', '<=', $filters['to']))
            ->latest('created_at')
            ->limit(5000)
            ->get();

        $headers = ['#', 'When', 'Action', 'Category', 'Severity', 'Summary', 'Actor', 'Role', 'Override', 'IP'];
        $data = $rows->values()->map(fn (AuditLog $log, int $index) => [
            $index + 1,
            optional($log->created_at)?->timezone(config('app.timezone'))->format('Y-m-d H:i:s'),
            $log->action,
            $log->category->label(),
            $log->severity->label(),
            $log->summary,
            $log->actor_name,
            $log->actor_role,
            $log->is_override ? 'Yes' : 'No',
            $log->ip_address,
        ]);

        return $this->exports->downloadCsv('psg-audit-logs.csv', $headers, $data);
    }
}
