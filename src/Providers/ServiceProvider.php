<?php

declare(strict_types=1);

namespace MikeBronner\FontAwesomeToFluxImporter\Providers;

use Illuminate\Support\ServiceProvider as BaseServiceProvider;
use MikeBronner\FontAwesomeToFluxImporter\Console\Commands\ImportCommand;

class ServiceProvider extends BaseServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . "/../../config/font-awesome-to-flux.php", "font-awesome-to-flux");
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                ImportCommand::class,
            ]);

            $this->publishes([
                __DIR__ . "/../../config/font-awesome-to-flux.php" => config_path("font-awesome-to-flux.php"),
            ], "font-awesome-to-flux-config");

            $this->publishes([
                __DIR__ . '/../../stubs/flux/icon.blade.php' => base_path('stubs/flux/icon.blade.php'),
            ], 'font-awesome-to-flux-stubs');
        }
    }
}
