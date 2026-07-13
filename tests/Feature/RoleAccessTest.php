<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_inactive_authenticated_users_are_logged_out_on_protected_routes(): void
    {
        $customer = User::factory()->customer()->inactive()->create();

        $this->actingAs($customer)
            ->get('/customer/appointments')
            ->assertRedirect(route('login', absolute: false));

        $this->assertGuest();
    }
}
