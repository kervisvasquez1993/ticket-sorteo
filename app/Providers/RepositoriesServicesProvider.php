<?php

namespace App\Providers;

use App\Interfaces\Auth\IAuthRepository;
use App\Interfaces\Auth\IAuthServices;
use App\Repository\Auth\AuthRepository;
use App\Services\Auth\AuthServices;
use Illuminate\Support\ServiceProvider;

class RepositoriesServicesProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->bind(IAuthRepository::class, AuthRepository::class);
        $this->app->bind(IAuthServices::class, AuthServices::class);
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
