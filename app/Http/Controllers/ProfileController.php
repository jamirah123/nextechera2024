<?php

namespace App\Http\Controllers;

use App\Http\Requests\Admin\UserAttachmentRules;
use App\Http\Requests\Profile\UpdatePasswordRequest;
use App\Http\Requests\Profile\UpdateProfileRequest;
use App\Models\UserAttachment;
use App\Services\UserAttachmentService;
use App\Support\Attachments\InlineAttachmentResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProfileController extends Controller
{
    public function __construct(private UserAttachmentService $attachments)
    {
    }

    public function show(Request $request): View
    {
        $user = $request->user()->load('attachments.uploader');

        return view('profile.show', [
            'user' => $user,
            'canManageDocuments' => $request->user()->can('manageAttachments', $user),
        ]);
    }

    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
        ]);
    }

    public function update(UpdateProfileRequest $request): RedirectResponse
    {
        $user = $request->user();

        $user->fill($request->validated());
        $user->save();

        return redirect()
            ->route('profile.show')
            ->with('status', 'Profile updated successfully.');
    }

    public function updatePassword(UpdatePasswordRequest $request): RedirectResponse
    {
        $user = $request->user();

        $user->forceFill([
            'password' => $request->validated('password'),
        ])->save();

        Auth::logoutOtherDevices($request->validated('password'));

        return redirect()
            ->route('profile.edit')
            ->with('status', 'Password updated successfully. Other sessions have been signed out.');
    }

    public function storeDocuments(Request $request): RedirectResponse
    {
        $user = $request->user();
        $this->authorize('manageAttachments', $user);

        $request->validate(UserAttachmentRules::rules());

        if ($request->hasFile('attachments')) {
            $this->attachments->storeMany(
                $user,
                $request->file('attachments'),
                $request->input('attachment_labels', []),
            );
        }

        return redirect()
            ->route('profile.show')
            ->with('status', 'Documents uploaded successfully.');
    }

    public function showAttachment(UserAttachment $attachment): View
    {
        $user = request()->user();
        $this->authorize('viewAttachments', $user);
        abort_unless($attachment->user_id === $user->id, 404);
        abort_unless(Storage::disk('local')->exists($attachment->path), 404);

        $attachment->load('uploader');

        return view('users.attachments.show', [
            'user' => $user,
            'attachment' => $attachment,
            'backRoute' => route('profile.show'),
            'streamRoute' => route('profile.attachments.stream', $attachment),
            'downloadRoute' => route('profile.attachments.download', $attachment),
            'destroyRoute' => route('profile.attachments.destroy', $attachment),
            'canManage' => request()->user()->can('manageAttachments', $user),
        ]);
    }

    public function streamAttachment(UserAttachment $attachment): StreamedResponse
    {
        $user = request()->user();
        $this->authorize('viewAttachments', $user);
        abort_unless($attachment->user_id === $user->id, 404);
        abort_unless(Storage::disk('local')->exists($attachment->path), 404);

        return (new InlineAttachmentResponse($attachment))->toResponse(request());
    }

    public function downloadAttachment(UserAttachment $attachment): StreamedResponse
    {
        $user = request()->user();
        $this->authorize('viewAttachments', $user);
        abort_unless($attachment->user_id === $user->id, 404);

        return Storage::disk('local')->download($attachment->path, $attachment->original_name);
    }

    public function destroyAttachment(UserAttachment $attachment): RedirectResponse
    {
        $user = request()->user();
        $this->authorize('manageAttachments', $user);
        abort_unless($attachment->user_id === $user->id, 404);

        $this->attachments->delete($attachment);

        return redirect()
            ->route('profile.show')
            ->with('status', 'Document removed.');
    }
}
