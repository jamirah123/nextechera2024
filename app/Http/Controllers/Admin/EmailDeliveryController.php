<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\WorkflowActionMail;
use App\Models\EmailDelivery;
use App\Models\SystemSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class EmailDeliveryController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', SystemSetting::class);

        $status = (string) $request->query('status', '');
        $deliveries = EmailDelivery::query()
            ->with('recipient:id,name', 'trigger:id,name')
            ->when(in_array($status, ['queued', 'sending', 'sent', 'failed', 'retrying'], true), fn ($query) => $query->where('status', $status))
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return view('admin.email-deliveries.index', [
            'deliveries' => $deliveries,
            'status' => $status,
        ]);
    }

    public function sendTest(Request $request): RedirectResponse
    {
        $this->authorize('update', SystemSetting::class);

        $data = $request->validate([
            'email' => ['required', 'email', 'max:190'],
        ]);

        $delivery = EmailDelivery::query()->create([
            'recipient_email' => $data['email'],
            'subject' => 'Test email',
            'action' => 'system.test_email',
            'priority' => 'normal',
            'status' => 'sending',
            'attempts' => 1,
            'triggered_by' => $request->user()?->id,
        ]);

        try {
            Mail::to($data['email'])->send(new WorkflowActionMail(
                headline: 'Test email',
                summary: 'This confirms that '.config('psg.company', config('app.name')).' can send operational email.',
                actorName: $request->user()?->name,
                actionUrl: route('settings.index'),
                actionLabel: 'Open platform settings',
                details: [
                    'Mailer: '.config('mail.default'),
                    'Sent at: '.now()->timezone(config('app.timezone'))->format('d M Y H:i'),
                ],
                priority: 'normal',
                deliveryId: $delivery->id,
            ));
        } catch (\Throwable $exception) {
            report($exception);
            $delivery->update([
                'status' => 'failed',
                'failure_reason' => 'The mail service could not deliver this message.',
            ]);

            return back()->with('error', 'Test email could not be sent. Check mail configuration and system logs.');
        }

        if ($delivery->fresh()->status !== 'sent') {
            $delivery->update([
                'status' => 'sent',
                'sent_at' => now(),
                'failure_reason' => null,
            ]);
        }

        return back()->with('status', 'Test email sent successfully.');
    }
}
