<?php

namespace TarasKoval\LaravelStarter;

use Illuminate\Support\ServiceProvider;
use TarasKoval\LaravelStarter\Console\Commands\StarterPublishCommand;

class LaravelStarterServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                StarterPublishCommand::class,
            ]);
        }
    }
}
