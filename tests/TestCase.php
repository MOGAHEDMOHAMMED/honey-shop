<?php

namespace Tests;

use Database\Seeders\CatalogPermissionsSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->app->detectEnvironment(static fn (): string => 'testing');
        config([
            'app.env' => 'testing',
            'database.default' => 'pgsql',
            'database.connections.pgsql.host' => 'postgres',
            'database.connections.pgsql.port' => '5432',
            'database.connections.pgsql.database' => 'honey_shop_test',
            'database.connections.pgsql.username' => 'honey_shop',
            'session.driver' => 'array',
            'cache.default' => 'array',
        ]);
        DB::purge();

        if (config('database.default') !== 'pgsql'
            || config('database.connections.pgsql.database') !== 'honey_shop_test') {
            throw new RuntimeException('Feature tests must use the isolated honey_shop_test database.');
        }

        DB::beginTransaction();

        try {
            (new CatalogPermissionsSeeder)->run();
        } catch (Throwable $exception) {
            DB::rollBack();
            throw $exception;
        }
    }

    protected function tearDown(): void
    {
        if (DB::transactionLevel() > 0) {
            DB::rollBack();
        }

        parent::tearDown();
    }
}
