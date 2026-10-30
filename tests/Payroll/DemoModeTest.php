<?php

namespace Tests\Payroll;

use Inertia\Testing\AssertableInertia;

/** The public demo: shared logins on the sign-in page, and nothing a visitor can do to lock the others out. */
class DemoModeTest extends PayrollTestCase
{
    public function test_the_demo_refuses_password_and_login_changes(): void
    {
        config(['demo.enabled' => true]);
        $this->postJson('/register', ['name' => 'x', 'email' => 'x@example.test', 'password' => 'secret123', 'password_confirmation' => 'secret123'])->assertForbidden();
        $this->postJson('/forgot-password', ['email' => 'x@example.test'])->assertForbidden();
        $this->postJson('/reset-password', ['token' => 't', 'email' => 'x@example.test', 'password' => 'p', 'password_confirmation' => 'p'])->assertForbidden();

        $this->actAs('admin');
        $this->deleteJson('/api/user/2')->assertForbidden()->assertJson(['message' => 'This is turned off in the demo, so every visitor can sign in with the shared logins.']);
        $this->actAs('employee', 7);
        $this->putJson('/api/user', ['password' => 'new-password'])->assertForbidden();
    }

    public function test_outside_the_demo_those_routes_are_not_blocked(): void
    {
        config(['demo.enabled' => false]);
        $this->actAs('employee', 7);
        $this->assertNotSame(403, $this->putJson('/api/user', [])->status());
    }

    public function test_the_sign_in_page_offers_the_demo_logins_only_on_the_demo(): void
    {
        $this->withoutVite();
        config(['demo.enabled' => true, 'demo.password' => 'try-me']);
        $this->get('/login')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Auth/Login')
            ->where('demo.password', 'try-me')
            ->where('demo.logins.0.username', 'admin')
            ->has('demo.logins', 3));

        config(['demo.enabled' => false]);
        $this->get('/login')->assertInertia(fn (AssertableInertia $page) => $page->where('demo', null));
    }

    public function test_the_demo_loader_refuses_production_unless_the_demo_is_on(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        config(['demo.enabled' => false]);
        $this->artisan('hris:demo')->expectsOutputToContain('only on the public demo server')->assertFailed();
    }
}
