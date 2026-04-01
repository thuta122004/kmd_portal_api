<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Illuminate\Support\Facades\Validator;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Password::defaults(function () {
            return Password::min(8)
                ->mixedCase()
                ->numbers()
                ->symbols()
                ->uncompromised() // Check against historical data breaches
                ->rules(['max:16']);
        });

        Validator::extend('kmd_email', function ($attribute, $value, $parameters, $validator) {
            // Regex for: prefix + @kmd.edu.mm
            return preg_match('/^[a-z0-9._%+-]+@kmd\.edu\.mm$/i', $value);
        }, 'The :attribute must be a valid @kmd.edu.mm email address.');
    }
}
