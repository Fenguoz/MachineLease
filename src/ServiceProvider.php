<?php

namespace Fenguoz\MachineLease;

use Illuminate\Support\ServiceProvider as BaseProvider;
use Illuminate\Support\Facades\Route;

class ServiceProvider extends BaseProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        //Include routes
        self::routes();

        if ($this->app->runningInConsole()) {
            // $this->registerMigrations();

            $this->commands([
                Commands\MachineCreate::class,
                Commands\MachineOutput::class,
                Commands\MachineQuotes::class,
            ]);
        }
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        // $this->app->singleton('admin', function () {
        //     return new Admin;
        // });
    }

    /**
     * Binds the routes into the controller.
     *
     * @param  callable|null  $callback
     * @param  array  $options
     * @return void
     */
    public static function routes($callback = null, array $options = [])
    {
        $callback = $callback ?: function ($router) {
            $router->all();
        };

        $defaultOptions = [
            'namespace' => '\Fenguoz\MachineLease\Http\Controllers',
        ];

        $options = array_merge($defaultOptions, $options);
        Route::group($options, function ($router) use ($callback) {
            $callback(new RouteRegistrar($router));
        });
    }

    /**
     * Register Passport's migration files.
     *
     * @return void
     */
    protected function registerMigrations()
    {
        if (Passport::$runsMigrations) {
            return $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        }

        $this->publishes([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'passport-migrations');
    }
}
