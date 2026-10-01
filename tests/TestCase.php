<?php

declare(strict_types=1);

namespace MikeBronner\FontAwesomeToFluxImporter\Tests;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use MikeBronner\FontAwesomeToFluxImporter\Providers\ServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        File::deleteDirectory(resource_path("views/flux/icon/fontawesome"));

        foreach (File::glob(storage_path("framework/fontawesome-*")) as $leftover) {
            File::deleteDirectory($leftover);
        }
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(resource_path("views/flux/icon/fontawesome"));
        File::deleteDirectory(storage_path("framework/testing/fontawesome"));

        parent::tearDown();
    }

    protected function getPackageProviders($app): array
    {
        return [
            ServiceProvider::class,
        ];
    }
}
