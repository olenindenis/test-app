<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Infrastructure\EventStore\EventStore;
use App\Infrastructure\Projections\EarningLineProjector;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('earning-lines:rebuild-projections')]
#[Description('Drop the earning line read models and rebuild them by replaying the event store')]
final class RebuildEarningLineProjections extends Command
{
    public function handle(EventStore $eventStore, EarningLineProjector $projector): int
    {
        $replayed = DB::transaction(function () use ($eventStore, $projector): int {
            DB::statement('TRUNCATE earning_line_adjustments, earning_lines');

            $count = 0;
            foreach ($eventStore->all() as $event) {
                $projector->project($event);
                $count++;
            }

            return $count;
        });

        $this->components->info("Replayed {$replayed} events into the earning line projections.");

        return self::SUCCESS;
    }
}
