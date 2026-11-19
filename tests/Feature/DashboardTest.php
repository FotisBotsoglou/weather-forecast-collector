<?php

namespace Tests\Feature;

use Tests\TestCase;

class DashboardTest extends TestCase
{
    public function test_the_dashboard_is_served_without_a_session(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/html; charset=utf-8')
            ->assertSee('Field Forecast')
            ->assertSee('/api/locations', false)
            ->assertCookieMissing('laravel_session');
    }
}
