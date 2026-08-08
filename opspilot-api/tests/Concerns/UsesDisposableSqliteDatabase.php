<?php

namespace Tests\Concerns;

use Illuminate\Support\Facades\DB;
use RuntimeException;

trait UsesDisposableSqliteDatabase
{
    private ?string $disposableDatabasePath = null;

    /** @var array<string, mixed> */
    private array $originalSqliteConfiguration = [];

    private string $originalDefaultConnection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalDefaultConnection = (string) config('database.default');
        $this->originalSqliteConfiguration = config('database.connections.sqlite');
        $path = tempnam(sys_get_temp_dir(), 'opspilot-test-');
        if ($path === false) {
            throw new RuntimeException('Unable to create a disposable SQLite database.');
        }

        $this->disposableDatabasePath = $path;
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite' => [
                'driver' => 'sqlite',
                'url' => null,
                'database' => $path,
                'prefix' => '',
                'foreign_key_constraints' => true,
                'busy_timeout' => null,
                'journal_mode' => null,
                'synchronous' => null,
                'transaction_mode' => 'DEFERRED',
            ],
        ]);
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');

        if (DB::connection()->getDatabaseName() !== $path) {
            throw new RuntimeException('The disposable SQLite database is not the active test database.');
        }

        $this->artisan('migrate', ['--force' => true])->assertExitCode(0);
    }

    protected function tearDown(): void
    {
        try {
            DB::disconnect('sqlite');
            DB::purge('sqlite');
            config([
                'database.default' => $this->originalDefaultConnection,
                'database.connections.sqlite' => $this->originalSqliteConfiguration,
            ]);
            DB::setDefaultConnection($this->originalDefaultConnection);

            if ($this->disposableDatabasePath !== null && file_exists($this->disposableDatabasePath)) {
                unlink($this->disposableDatabasePath);
            }
        } finally {
            parent::tearDown();
        }
    }
}
