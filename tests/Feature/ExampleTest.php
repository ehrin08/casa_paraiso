<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_fast_navigation_asset_defines_turbo_lifecycle_and_exclusions(): void
    {
        $script = file_get_contents(resource_path('js/app.js'));

        $this->assertIsString($script);
        $this->assertStringContainsString('turboSession.drive = false', $script);
        $this->assertStringContainsString("'turbo:before-render'", $script);
        $this->assertStringContainsString("'turbo:visit'", $script);
        $this->assertStringContainsString("'turbo:load'", $script);
        $this->assertStringContainsString("'turbo:before-cache'", $script);
        $this->assertStringContainsString("link.hasAttribute('data-panel-link')", $script);
        $this->assertStringContainsString("url.pathname.includes('/export')", $script);
        $this->assertStringContainsString("form.method.toLowerCase() !== 'get'", $script);
        $this->assertStringNotContainsString("prefetch.rel = 'prefetch'", $script);
    }

    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/');

        $response
            ->assertStatus(200)
            ->assertSee('data-page-loading', false)
            ->assertSee('data-turbo-track="reload"', false)
            ->assertDontSee('data-prefetch', false)
            ->assertSee('GAIA TOUCH')
            ->assertSee('AURORA BREEZE')
            ->assertSee('PHP 499.00')
            ->assertSee('PHP 849.00')
            ->assertSee('Ventosa')
            ->assertSee('1:00 PM to 12:00 MN')
            ->assertSee('Reserve your spot. You deserve this.');
    }
}
