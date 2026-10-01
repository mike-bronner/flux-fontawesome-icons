<?php

declare(strict_types=1);

namespace MikeBronner\FontAwesomeToFluxImporter\Providers;

use Illuminate\Support\ServiceProvider as BaseServiceProvider;
use MikeBronner\FontAwesomeToFluxImporter\Console\Commands\ImportCommand;

class ServiceProvider extends BaseServiceProvider
{
    protected const CONFIG_PATH = __DIR__ . "/../../config/font-awesome-to-flux.php";
    protected const STUB_PATH = __DIR__ . "/../../stubs/flux/icon.blade.php";

    public function register(): void
    {
        $this->mergeConfigFrom(self::CONFIG_PATH, "font-awesome-to-flux");
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                ImportCommand::class,
            ]);

            $this->publishes([
                self::CONFIG_PATH => config_path("font-awesome-to-flux.php"),
            ], "font-awesome-to-flux-config");

            $this->publishes([
                self::STUB_PATH => base_path("stubs/flux/icon.blade.php"),
            ], "font-awesome-to-flux-stubs");
        }
    }
}
