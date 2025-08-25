<?php

namespace EmranAlhaddad\ContentSync;

use Illuminate\Support\ServiceProvider;
use Statamic\Facades\Utility;
use Statamic\Statamic;

class ContentSyncServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/content-sync.php', 'content-sync');
    }

    public function boot(): void
    {
        // Publish config
        $this->publishes([
            __DIR__ . '/../config/content-sync.php' => config_path('content-sync.php'),
        ], 'content-sync-config');

        // Optional Statamic config stub
        if (is_file(__DIR__ . '/../config/statamic/content-sync.example.php')) {
            $this->publishes([
                __DIR__ . '/../config/statamic/content-sync.example.php' => config_path('statamic/content-sync.php'),
            ], 'content-sync-config');
        }

        // Load routes & views
        $this->loadRoutesFrom(__DIR__ . '/../routes/cp.php');
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'statamic-content-sync');

        // ✅ Register CP JS correctly (vendor handle + relative filename)
        // This will load: public/vendor/statamic-content-sync/js/content-sync.js
        Statamic::script('statamic-content-sync', 'content-sync.js');
        Statamic::style('statamic-content-sync', 'css/content-sync.css'); 

        // (If you also have CSS, uncomment)
        // Statamic::style('statamic-content-sync', 'content-sync.css');

        // Register the CP Utility
        Utility::extend(function () {
            Utility::register('content_sync')
                ->title('Content Sync')
                ->navTitle('Content Sync')
                ->icon('history')
                ->view('statamic-content-sync::utility');
        });

        // Publish built assets to the expected public path
        $this->publishes([
            __DIR__ . '/../resources/dist' => public_path('vendor/statamic-content-sync'),
        ], 'content-sync-assets');
    }
}
