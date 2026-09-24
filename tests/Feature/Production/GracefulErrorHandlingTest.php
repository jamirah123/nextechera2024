<?php

namespace Tests\Feature\Production;

use Tests\TestCase;

class GracefulErrorHandlingTest extends TestCase
{
    public function test_not_found_page_is_friendly_and_non_technical(): void
    {
        config(['app.debug' => false]);

        $response = $this->get('/this-route-does-not-exist-'.uniqid());

        $response->assertNotFound();
        $response->assertSee('Page not found', false);
        $response->assertDontSee('SQLSTATE');
        $response->assertDontSee('Stack trace');
        $response->assertDontSee(base_path());
    }

    public function test_server_error_view_uses_safe_user_message(): void
    {
        $html = view('errors.500')->render();

        $this->assertStringContainsString(
            'The request could not be completed at this time. Please try again.',
            $html,
        );
        $this->assertStringNotContainsString('SQLSTATE', $html);
        $this->assertStringNotContainsString('Stack trace', $html);
    }

    public function test_too_many_requests_view_is_present(): void
    {
        $html = view('errors.429')->render();

        $this->assertStringContainsString('Too many requests', $html);
    }
}
