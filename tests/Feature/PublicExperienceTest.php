<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class PublicExperienceTest extends TestCase
{
    public function test_customer_registration_is_provisioned_through_google_only(): void
    {
        $this->assertFalse(Route::has('register'));

        $this->get('/login')
            ->assertOk()
            ->assertSee('Continue with your verified Google account to register or sign in as a customer.')
            ->assertDontSee('Create an account');
    }

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

    public function test_landing_page_presents_the_canonical_catalog_and_real_time_booking_flow(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('data-page-loading', false)
            ->assertSee('data-turbo-track="reload"', false)
            ->assertDontSee('data-prefetch', false)
            ->assertSee('GAIA TOUCH')
            ->assertSee('AURORA BREEZE')
            ->assertSee('PHP 499.00')
            ->assertSee('PHP 849.00')
            ->assertSee('Ventosa')
            ->assertSee(config('casa.business_hours.window'))
            ->assertSee('Reserve your spot. You deserve this.')
            ->assertSee('Your time and therapist are reserved as soon as booking succeeds.')
            ->assertDontSee('Request-first care');
    }
}
