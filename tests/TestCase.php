<?php

declare(strict_types=1);

namespace MikeBronner\FontAwesomeToFluxImporter\Tests;

use Illuminate\Console\Events\CommandStarting;
use Illuminate\Foundation\Testing\WithConsoleEvents;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use MikeBronner\FontAwesomeToFluxImporter\Providers\ServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;
use Override;

abstract class TestCase extends BaseTestCase
{
    use WithConsoleEvents;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        Event::listen(CommandStarting::class, $this->readPromptAnswersFromEmptyInput(...));
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

    protected function readPromptAnswersFromEmptyInput(CommandStarting $event): void
    {
        $input = data_get($event, "input");
        $input->setStream(fopen("php://memory", "r"));
    }

    #[Override]
    protected function getPackageProviders($app): array
    {
        return [
            ServiceProvider::class,
        ];
    }
}
