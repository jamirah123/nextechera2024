<?php

namespace Tests\Feature\Production;

use App\Http\Middleware\PreventRapidDuplicatePosts;
use App\Jobs\CalculatePayrollRunJob;
use App\Support\Errors\UserFacingExceptionRenderer;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Http\Request;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use InvalidArgumentException;
use RuntimeException;
use Tests\TestCase;

class UserFacingFailureTest extends TestCase
{
    public function test_unexpected_errors_stay_friendly_when_debug_is_enabled(): void
    {
        config(['app.debug' => true]);

        Route::middleware('web')->get('/_test/boom', function (): void {
            throw new RuntimeException('SQLSTATE[HY000] leaked '.base_path('app/Secret.php'));
        });

        $response = $this->get('/_test/boom');

        $response->assertServerError();
        $response->assertSee('Something went wrong', false);
        $response->assertSee('Your data has not been lost', false);
        $response->assertSee('Try again', false);
        $response->assertSee('Reference', false);
        $response->assertDontSee('SQLSTATE', false);
        $response->assertDontSee('Secret.php', false);
        $response->assertDontSee(base_path(), false);
        $response->assertHeader('X-Request-Id');
    }

    public function test_business_rule_returns_to_the_form_with_a_specific_message(): void
    {
        Route::middleware('web')->post('/_test/overlap', function (): void {
            throw new InvalidArgumentException('This guard is already assigned to another overlapping shift.');
        });

        $this->from('/dashboard')
            ->post('/_test/overlap', ['guard_id' => 5, 'site_id' => 2])
            ->assertRedirect('/dashboard')
            ->assertSessionHas('error', 'This guard is already assigned to another overlapping shift.')
            ->assertSessionHasInput('guard_id', '5');
    }

    public function test_json_failures_do_not_include_a_stack_trace(): void
    {
        config(['app.debug' => true]);

        Route::middleware('web')->post('/_test/json-boom', function (): void {
            throw new RuntimeException('SQLSTATE[42S02] table missing '.base_path());
        });

        $response = $this->postJson('/_test/json-boom');

        $response->assertServerError();
        $response->assertJsonPath(
            'message',
            'Something went wrong. We couldn\'t complete this action. Please try again. If the problem continues, contact the administrator.',
        );
        $this->assertStringNotContainsString('SQLSTATE', $response->getContent());
        $this->assertStringNotContainsString(base_path(), $response->getContent());
        $this->assertArrayNotHasKey('trace', $response->json());
    }

    public function test_invalid_upload_is_a_field_error(): void
    {
        Route::middleware('web')->post('/_test/upload', function () {
            request()->validate([
                'document' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            ]);
        });

        $this->from('/dashboard')
            ->post('/_test/upload', [
                'document' => \Illuminate\Http\UploadedFile::fake()->create('notes.exe', 12, 'application/octet-stream'),
            ])
            ->assertRedirect('/dashboard')
            ->assertSessionHasErrors('document');
    }

    public function test_missing_page_and_expired_form_use_branded_pages(): void
    {
        config(['app.debug' => true]);

        $this->get('/missing-'.uniqid())
            ->assertNotFound()
            ->assertSee('Page not found', false)
            ->assertDontSee('SQLSTATE', false);

        $expired = Request::create('/guards', 'POST');
        $expired->headers->set('Accept', 'text/html');
        $page = app(UserFacingExceptionRenderer::class)->render(new TokenMismatchException('CSRF token mismatch.'), $expired);

        $this->assertSame(419, $page->getStatusCode());
        $this->assertStringContainsString('This page has expired', $page->getContent());
        $this->assertStringNotContainsString('TokenMismatch', $page->getContent());
    }

    public function test_guest_is_sent_to_sign_in_without_a_technical_page(): void
    {
        $this->get('/dashboard')
            ->assertRedirect('/login');
    }

    public function test_in_flight_duplicate_submission_is_rejected(): void
    {
        $request = Request::create('/deployments', 'POST', ['guard_id' => 1]);
        $request->headers->set('Accept', 'application/json');
        $key = 'form-submit:'.PreventRapidDuplicatePosts::fingerprint($request);
        Cache::put($key, 1, 30);

        $called = false;
        $response = app(PreventRapidDuplicatePosts::class)->handle($request, function () use (&$called) {
            $called = true;

            return response('saved');
        });

        $this->assertFalse($called);
        $this->assertSame(409, $response->getStatusCode());
        $this->assertStringContainsString('already being processed', $response->getContent());
    }

    public function test_validation_errors_remain_field_level(): void
    {
        Route::middleware('web')->post('/_test/employee', function () {
            request()->validate([
                'employment_id' => ['required', 'string'],
            ]);
        });

        $this->postJson('/_test/employee', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('employment_id');
    }

    public function test_failed_payroll_job_is_logged_for_administrators(): void
    {
        $logged = false;
        Log::listen(function (MessageLogged $event) use (&$logged): void {
            if (
                str_contains($event->message, 'Payroll calculation job failed')
                && ($event->context['payroll_run_id'] ?? null) === 44
                && str_contains((string) ($event->context['message'] ?? ''), 'SQLSTATE')
            ) {
                $logged = true;
            }
        });

        (new CalculatePayrollRunJob(44))->failed(new RuntimeException('SQLSTATE[HY000] connection lost'));

        $this->assertTrue($logged);
    }
}
