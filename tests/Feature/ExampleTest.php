<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_spa_entrypoint_renders_without_built_assets(): void
    {
        $this->withoutVite();

        $response = $this->get('/');

        $response
            ->assertOk()
            ->assertSee('id="app"', false);
    }
}
