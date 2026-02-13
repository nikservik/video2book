<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/');

        $response
            ->assertStatus(200)
            ->assertSee('data-theme-set="light"', false)
            ->assertSee('data-theme-set="dark"', false)
            ->assertSee('data-settings-trigger', false)
            ->assertDontSee('View notifications')
            ->assertDontSee('Open user menu');
    }
}
