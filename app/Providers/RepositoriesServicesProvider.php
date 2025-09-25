<?php

namespace App\Providers;

use App\Interfaces\Auth\IAuthRepository;
use App\Interfaces\Auth\IAuthServices;
use App\Interfaces\Event\IEventRepository;
use App\Interfaces\Event\IEventServices;
use App\Repository\Auth\AuthRepository;
use App\Repository\Event\EventRepository;
use App\Services\Auth\AuthServices;
use App\Services\Event\EventServices;
use Illuminate\Support\ServiceProvider;

class RepositoriesServicesProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->bind(IEventRepository::class, EventRepository::class);
        $this->app->bind(IEventServices::class, EventServices::class);
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
