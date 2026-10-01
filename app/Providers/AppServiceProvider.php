<?php

namespace App\Providers;

use App\Domain\EarningLine\EarningLineRepository;
use App\Infrastructure\EventStore\EventStore;
use App\Infrastructure\EventStore\PostgresEventStore;
use App\Infrastructure\Persistence\EventSourcedEarningLineRepository;
use App\Infrastructure\Projections\EarningLineProjector;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $bindings = [
        EventStore::class => PostgresEventStore::class,
        EarningLineRepository::class => EventSourcedEarningLineRepository::class,
    ];

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Event::subscribe(EarningLineProjector::class);
    }
}
