<?php

namespace Tests\Feature;

use Tests\TestCase;

class TestingDatabaseConfigurationTest extends TestCase
{
    public function test_testing_environment_uses_sqlite_in_memory_database(): void
    {
        $this->assertSame('testing', config('app.env'));
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
    }
}
