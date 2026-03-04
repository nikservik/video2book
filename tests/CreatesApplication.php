<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use RuntimeException;

trait CreatesApplication
{
    /**
     * Creates the application.
     */
    public function createApplication()
    {
        $this->applyTestingEnvironment();

        $app = require __DIR__.'/../bootstrap/app.php';

        $app->make(Kernel::class)->bootstrap();
        $defaultConnection = (string) $app['config']->get('database.default');
        $sqliteDatabase = (string) $app['config']->get('database.connections.sqlite.database');

        if ($defaultConnection !== 'sqlite') {
            throw new RuntimeException(sprintf(
                'Testing database driver must be sqlite. Current driver: "%s".',
                $defaultConnection
            ));
        }

        if ($sqliteDatabase !== ':memory:') {
            throw new RuntimeException(sprintf(
                'Testing SQLite database must be ":memory:". Current database: "%s".',
                $sqliteDatabase
            ));
        }

        return $app;
    }

    private function applyTestingEnvironment(): void
    {
        $this->setEnvironmentVariable('APP_ENV', 'testing');
        $this->setEnvironmentVariable('DB_CONNECTION', 'sqlite');
        $this->setEnvironmentVariable('DB_DATABASE', ':memory:');
        $this->setEnvironmentVariable('DB_URL', '');
    }

    private function setEnvironmentVariable(string $key, string $value): void
    {
        putenv($key.'='.$value);
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }
}
