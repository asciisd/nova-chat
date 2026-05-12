<?php

namespace Asciisd\NovaChat;

use Asciisd\NovaChat\Console\Commands\MakeTableCommand;
use Asciisd\NovaChat\Support\BlockList;
use Asciisd\NovaChat\Support\TopicRegistry;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Laravel\Nova\Nova;

class NovaChatServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/nova-chat.php', 'nova-chat');

        $this->app->singleton(TopicRegistry::class, fn () => new TopicRegistry);
        $this->app->singleton(BlockList::class, fn () => new BlockList);
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/nova-chat.php' => config_path('nova-chat.php'),
        ], 'nova-chat-config');

        $this->publishes([
            __DIR__ . '/../database/stubs' => database_path('stubs/nova-chat'),
        ], 'nova-chat-stubs');

        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([MakeTableCommand::class]);
        }

        $this->registerMorphMap();
        $this->registerRoutes();
    }

    protected function registerMorphMap(): void
    {
        $map = (array) config('nova-chat.morph_map', []);

        if (! empty($map)) {
            Relation::enforceMorphMap($map);
        }
    }

    protected function registerRoutes(): void
    {
        Route::middleware(config('nova.api_middleware', ['nova']))
            ->prefix('nova-vendor/nova-chat')
            ->group(function () {
                $this->loadRoutesFrom(__DIR__ . '/../routes/api.php');
            });

        Nova::router(['nova', \Laravel\Nova\Http\Middleware\Authenticate::class], 'nova-chat')
            ->group(__DIR__ . '/../routes/inertia.php');
    }
}
