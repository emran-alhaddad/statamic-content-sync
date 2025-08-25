<?php

namespace EmranAlhaddad\ContentSync;

use Illuminate\Support\ServiceProvider;
use Statamic\CP\Utilities\Utility;
use Statamic\Statamic;

class ContentSyncServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/content-sync.php', 'content-sync');

        // (Optional) bind custom services here if you wire the SOLID layer
    }

    public function boot(): void
    {
        // Config publish
        $this->publishes([
            __DIR__ . '/../config/content-sync.php' => config_path('content-sync.php'),
        ], 'content-sync-config');

        // Optional statamic config stub
        if (is_file(__DIR__ . '/../config/statamic/content-sync.example.php')) {
            $this->publishes([
                __DIR__ . '/../config/statamic/content-sync.example.php' => config_path('statamic/content-sync.php'),
            ], 'content-sync-config');
        }

        // Routes, views, assets
        $this->loadRoutesFrom(__DIR__ . '/../routes/cp.php');
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'statamic-content-sync');

        // Serve CP JS
        Statamic::script('content-sync', __DIR__ . '/../resources/dist/js/content-sync.js');

        // Utility registration
        Utility::register('content-sync', function ($utility) {
            $utility->title('Content Sync')
                ->icon('history')
                ->view('statamic-content-sync::utility');
        });

        // Optional publish of built assets to public path
        $this->publishes([
            __DIR__ . '/../resources/dist' => public_path('vendor/statamic-content-sync'),
        ], 'content-sync-assets');
    }
}
