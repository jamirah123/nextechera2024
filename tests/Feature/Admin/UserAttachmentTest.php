<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\User;
use App\Models\UserAttachment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UserAttachmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_upload_and_view_own_documents_on_profile(): void
    {
        Storage::fake('local');

        $user = User::factory()->role(UserRole::ShiftManager)->create();
        $document = UploadedFile::fake()->create('appointment-letter.pdf', 120, 'application/pdf');

        $this->actingAs($user)
            ->post(route('profile.attachments.store'), [
                'attachments' => [$document],
            ])
            ->assertRedirect(route('profile.show'))
            ->assertSessionHas('status');

        $this->assertDatabaseCount('user_attachments', 1);

        $attachment = UserAttachment::query()->firstOrFail();
        Storage::disk('local')->assertExists($attachment->path);

        $this->actingAs($user)
            ->get(route('profile.show'))
            ->assertOk()
            ->assertSee('appointment-letter.pdf', false)
            ->assertSee('View', false);

        $this->actingAs($user)
            ->get(route('profile.attachments.show', $attachment))
            ->assertOk()
            ->assertSee('Document viewer', false);

        $this->actingAs($user)
            ->get(route('profile.attachments.download', $attachment))
            ->assertOk();

        $this->actingAs($user)
            ->from(route('profile.attachments.show', $attachment))
            ->delete(route('profile.attachments.destroy', $attachment))
            ->assertRedirect(route('profile.show'));

        $this->assertDatabaseMissing('user_attachments', ['id' => $attachment->id]);
    }

    public function test_super_admin_can_manage_documents_for_any_user(): void
    {
        Storage::fake('local');

        $admin = User::factory()->superAdmin()->create();
        $target = User::factory()->role(UserRole::HrManager)->create();
        $document = UploadedFile::fake()->create('hr-cert.pdf', 80, 'application/pdf');

        $this->actingAs($admin)
            ->put(route('users.update', $target), [
                'name' => $target->name,
                'email' => $target->email,
                'phone' => $target->phone,
                'role' => $target->role->value,
                'is_active' => true,
                'attachments' => [$document],
            ])
            ->assertRedirect(route('users.show', $target));

        $attachment = UserAttachment::query()->where('user_id', $target->id)->firstOrFail();

        $this->actingAs($admin)
            ->get(route('users.show', $target))
            ->assertOk()
            ->assertSee('hr-cert.pdf', false);

        $this->actingAs($admin)
            ->delete(route('users.attachments.destroy', [$target, $attachment]))
            ->assertRedirect(route('users.show', $target));

        $this->assertDatabaseMissing('user_attachments', ['id' => $attachment->id]);
    }

    public function test_user_cannot_access_another_users_documents(): void
    {
        Storage::fake('local');

        $owner = User::factory()->role(UserRole::FinanceManager)->create();
        $other = User::factory()->role(UserRole::OperationsManager)->create();

        $this->actingAs($owner)
            ->post(route('profile.attachments.store'), [
                'attachments' => [UploadedFile::fake()->create('bank-letter.pdf', 50, 'application/pdf')],
            ]);

        $attachment = UserAttachment::query()->firstOrFail();

        $this->actingAs($other)
            ->get(route('profile.attachments.download', $attachment))
            ->assertNotFound();
    }

    public function test_non_super_admin_cannot_access_admin_user_document_routes(): void
    {
        Storage::fake('local');

        $admin = User::factory()->superAdmin()->create();
        $manager = User::factory()->role(UserRole::ShiftManager)->create();
        $other = User::factory()->role(UserRole::OperationsManager)->create();

        $this->actingAs($admin)
            ->put(route('users.update', $manager), [
                'name' => $manager->name,
                'email' => $manager->email,
                'phone' => $manager->phone,
                'role' => $manager->role->value,
                'is_active' => true,
                'attachments' => [UploadedFile::fake()->create('policy.pdf', 40, 'application/pdf')],
            ]);

        $attachment = UserAttachment::query()->firstOrFail();

        $this->actingAs($other)
            ->get(route('users.show', $manager))
            ->assertForbidden();

        $this->actingAs($other)
            ->get(route('users.attachments.download', [$manager, $attachment]))
            ->assertForbidden();
    }

    public function test_user_can_open_word_document_in_system_viewer(): void
    {
        Storage::fake('local');

        $user = User::factory()->role(UserRole::HrManager)->create();
        $document = UploadedFile::fake()->create('contract.docx', 120, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');

        $this->actingAs($user)
            ->post(route('profile.attachments.store'), [
                'attachments' => [$document],
            ])
            ->assertRedirect(route('profile.show'));

        $attachment = UserAttachment::query()->firstOrFail();

        $this->actingAs($user)
            ->get(route('profile.attachments.show', $attachment))
            ->assertOk()
            ->assertSee('Document viewer', false)
            ->assertSee('contract.docx', false)
            ->assertSee('Open in browser tab', false)
            ->assertSee('Loading document preview', false);

        $this->actingAs($user)
            ->get(route('profile.attachments.stream', $attachment))
            ->assertOk()
            ->assertHeader('content-disposition');
    }
}
