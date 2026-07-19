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

        $response->assertStatus(200);
    }

    public function test_dependency_heavy_welcome_resolves_all_dependencies(): void
    {
        $response = $this->get('/perf/dependency-heavy');

        $response
            ->assertOk()
            ->assertHeader('X-Benchmark-Checksum', '666');
    }
}
