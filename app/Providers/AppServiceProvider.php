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
    public array $bindings = [
        EventStore::class => PostgresEventStore::class,
        EarningLineRepository::class => EventSourcedEarningLineRepository::class,
    ];

    public function boot(): void
    {
        Event::subscribe(EarningLineProjector::class);
    }
}
